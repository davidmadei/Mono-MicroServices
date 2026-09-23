"""
locustfile.py -- Lab 02: Monolithic vs. Microservices on AWS Elastic Beanstalk

===========================================================================
WHAT --host POINTS AT  (read this before anything else)
===========================================================================
Monolithic run:
    --host http://<monolith-env>.<id>.<region>.elasticbeanstalk.com

Microservices run:
    --host http://<routerequest-env>.<id>.<region>.elasticbeanstalk.com
           ^^^^ the RouteRequest environment, and ONLY that one.

This is the part that trips people up, so it is worth stating plainly:

In the microservices deployment there are THREE Beanstalk environments --
RouteRequest, DisplayProducts, and BuyProducts -- each with its own URL, its
own load balancer, and its own autoscaling group. But Locust only ever talks
to RouteRequest.

DisplayProducts and BuyProducts are reached SERVER-SIDE, by PHP's
file_get_contents() running inside the RouteRequest instances. Their URLs are
hardcoded into products.php before deployment (the $displayProductsMicroService
and $buyProductsMicroService variables). They receive no traffic from the
public internet and are invisible to the load generator.

That is precisely why slide 6 can show DisplayProducts and BuyProducts scaling
out: they are responding to load FORWARDED to them by RouteRequest, not to
users. The traffic reaching them is machine-to-machine, one hop deeper than
anything Locust can see.

Consequence for your numbers: the response time Locust reports for a buy is
    RouteRequest queue + PHP + HTTP hop + BuyProducts queue + PHP + RDS
all collapsed into one measurement. Locust cannot decompose it. Only
CloudWatch, per environment, can tell you which tier the time went to. Say
this out loud in class -- it is the clearest example of why distributed
systems need distributed tracing.

===========================================================================
HOW TO RUN A CAPTURE (pre-recorded demo)
===========================================================================
1. Reset inventory first (see reset_inventory.sql). Non-negotiable -- read
   the comments in that file for why.
2. Make sure every environment under test (one for the monolith, three for
   microservices) is scaled down to its minimum and has been idle for ~10
   minutes, so the run starts from a known state.
3. Run headless and keep everything:

    mkdir -p runs

    locust -f locustfile.py \
      --host http://<monolith-env-url> \
      --headless --csv=runs/mono_readheavy --csv-full-history \
      --html=runs/mono_readheavy.html

4. Reset inventory again, wait for scale-down, then repeat with the
   RouteRequest host and --csv=runs/micro_readheavy.

5. Repeat both for the write-heavy mix (see THE EXPERIMENT KNOB below):
   VIEW_WEIGHT=1 BUY_WEIGHT=8 ... --csv=runs/mono_writeheavy
   VIEW_WEIGHT=1 BUY_WEIGHT=8 ... --csv=runs/micro_writeheavy
   Four runs in all.

--csv-full-history is the important flag. It writes *_stats_history.csv with
one row every 10 seconds containing user count, RPS, failure rate, and the
p50/p95/p99 latencies. That file is what you align against CloudWatch to show
the scale-out gap. Without it you get only end-of-run totals, which hide
exactly the moment you want to teach.

The UTC timestamps printed at start and stop (see the event hooks at the
bottom) are there so you can line Locust's timeline up with CloudWatch, which
always displays in UTC.
"""

import os
import random
from datetime import datetime, timezone

from locust import HttpUser, LoadTestShape, between, events, task


# ===========================================================================
# THE EXPERIMENT KNOB
# ===========================================================================
# burn_cpu() -- 15,000 rounds of SHA-256 -- runs ONLY on the buy path. So this
# ratio decides how much load lands on BuyProducts vs. DisplayProducts, which
# is what makes the microservices split pay off. Or not.
#
#   8 / 1  read-heavy.  Only BuyProducts needs to scale; DisplayProducts and
#                       RouteRequest stay near minimum. Microservices wins.
#   1 / 8  write-heavy. Everything scales, and the shared Inventory table lock
#                       becomes the ceiling in BOTH architectures. The
#                       advantage largely collapses.
#
# Run the lab twice changing only these two numbers and students discover that
# the benefit is a property of the WORKLOAD, not of the architecture.
#
# Override without editing the file:
#   VIEW_WEIGHT=1 BUY_WEIGHT=8 locust -f locustfile.py --host ... --csv=runs/mono_writeheavy
VIEW_WEIGHT = int(os.getenv("VIEW_WEIGHT", "8"))
BUY_WEIGHT = int(os.getenv("BUY_WEIGHT", "1"))

# String that addCustomerOrder() echoes when an order actually reaches the DB.
# Present in both Monolithic/products.php and Microservices/buyProducts.php.
ORDER_OK = "Inserted new order successfully"


class WebstoreUser(HttpUser):
    # Think time between requests. Lowering this raises RPS without adding
    # users -- the cheapest way to push CPU past the scale-up threshold if the
    # instance type turns out to be bigger than the load.
    wait_time = between(1, 3)

    def on_start(self):
        """Every simulated user lands on the product page first."""
        self.view_products()

    @task(VIEW_WEIGHT)
    def view_products(self):
        # name= collapses all of these into ONE row in Locust's stats table.
        # Without it, varying query strings produce thousands of useless rows.
        self.client.get("/products.php?action=view", name="/products.php (view)")

    @task(BUY_WEIGHT)
    def buy_product(self):
        item_id = random.randint(1, 3)
        quantity = random.randint(1, 2)
        customer_email = f"user_{random.randint(1, 100000)}@example.com"

        url = (
            "/products.php?action=buy"
            f"&itemID={item_id}"
            f"&quantity={quantity}"
            f"&customerEmail={customer_email}"
        )

        # The app returns HTTP 200 no matter what happens: out of stock, DB
        # error, a service it cannot reach. The status code tells us nothing,
        # so we key on the text the app prints only when an order is written.
        #
        # Without this block Locust reports 0.00% failures for the entire run
        # and the class never sees the system actually degrade.
        with self.client.get(
            url, name="/products.php (buy)", catch_response=True
        ) as response:
            if ORDER_OK in response.text:
                response.success()
            else:
                response.failure("HTTP 200 but no order was recorded")


class StagedLoadShape(LoadTestShape):
    """
    Reproduces the load profile shown on slide 4 of the lab deck.

    IMPORTANT: "duration" is the CUMULATIVE second at which a stage ENDS, not
    the stage's length.

    Stages 1 and 2 both target 500 users. At spawn_rate 20 the ramp finishes in
    about 25 seconds, so together they are really one 8-minute hold at 500.
    That is intentional: Beanstalk needs several minutes to notice the CPU
    breach, launch an instance, pass health checks, and start sending it
    traffic. Watch p95 during that window -- the gap between "alarm fired" and
    "new host is serving" is paid for by users, and it does not appear in the
    CloudWatch CPU chart at all.

    NOTE: because this class exists, Locust IGNORES -u / -r on the command line
    and the user-count boxes in the web UI. The shape is in charge. That is
    deliberate -- both architectures must see an identical profile or the
    comparison is worthless.
    """

    stages = [
        # ends at | users | spawn_rate
        {"duration": 240, "users": 500, "spawn_rate": 20},  # 00:00-04:00  ramp to 500
        {"duration": 480, "users": 500, "spawn_rate": 20},  # 04:00-08:00  hold at 500
        {"duration": 720, "users": 700, "spawn_rate": 20},  # 08:00-12:00  step to 700
        {"duration": 900, "users": 50, "spawn_rate": 50},   # 12:00-15:00  cool down
    ]

    def tick(self):
        run_time = self.get_run_time()
        for stage in self.stages:
            if run_time < stage["duration"]:
                return (stage["users"], stage["spawn_rate"])
        return None  # past the last stage -> Locust stops the test


# ===========================================================================
# RUN METADATA
# ===========================================================================
# CloudWatch displays in UTC. Locust's CSVs are in local time. These two hooks
# print UTC timestamps at start and stop so you can align the two timelines
# when you build the slides. Copy them into your capture notes.

@events.test_start.add_listener
def on_test_start(environment, **kwargs):
    print("=" * 70)
    print("[lab02] host          :", environment.host)
    print(f"[lab02] weights       : view={VIEW_WEIGHT} buy={BUY_WEIGHT}")
    print("[lab02] started (UTC) :", datetime.now(timezone.utc).isoformat())
    print("[lab02] >>> note this timestamp for CloudWatch alignment <<<")
    print("=" * 70)


@events.test_stop.add_listener
def on_test_stop(environment, **kwargs):
    s = environment.stats.total
    pct = (s.num_failures / s.num_requests * 100) if s.num_requests else 0.0
    print("=" * 70)
    print("[lab02] stopped (UTC) :", datetime.now(timezone.utc).isoformat())
    print(f"[lab02] requests      : {s.num_requests}")
    print(f"[lab02] failures      : {s.num_failures} ({pct:.2f}%)")
    print(f"[lab02] p50 / p95 /p99: "
          f"{s.get_response_time_percentile(0.5)} / "
          f"{s.get_response_time_percentile(0.95)} / "
          f"{s.get_response_time_percentile(0.99)} ms")
    print("=" * 70)
