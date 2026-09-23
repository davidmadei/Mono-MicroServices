# Deploy the Monolithic Webstore to AWS (PaaS)

Verified against the AWS consoles as of September 2026. This replaces the original
instructions, which were written against an older console and contained one error
that silently breaks the lab.

A changelog of every difference, with reasons, is at the bottom.

---

## Before you start

You need:

- An AWS account
- **dbeaver** (<https://dbeaver.io/>) or a `mysql` client
- **Python + Locust** (`pip install locust`) — for the load test in Lab 02

You do **not** need Apache, PHP, MySQL, or Docker on your own machine. Beanstalk
installs Apache and PHP on the instances it creates; RDS installs MySQL. That is what
you are paying the platform to do.

> **Cost warning.** AWS restructured the Free Tier in July 2025 — new accounts get
> $200 in credits and a 6-month plan rather than the old 12-month free tier. A
> load-balanced Beanstalk environment with scaling headroom plus an RDS instance is
> **not** fully covered. Follow the teardown in Section 5 when you are done, and check
> your billing dashboard.

---

## 1. Create the database (Amazon RDS)

### 1.1 Launch the instance

1. AWS Console → **RDS** → **Create database**.
2. **Engine type:** MySQL.
3. **Database creation method: Full configuration.**

   > The console labels these **Full configuration** and **Easy create**; the AWS docs
   > call the first one "Standard create." Same thing.
   >
   > **Either works, but Full configuration is smoother.** Easy create lets you set the
   > instance size, identifier, username and password, but takes defaults for everything
   > else — including **Publicly accessible: No** and the `default` VPC security group.
   > Both are editable after creation (the "View default settings for Easy create" panel
   > tells you which are), so Easy create costs you a Modify-and-wait cycle rather than a
   > restart. Full configuration just lets you set them up front.
   >
   > If you have already created the instance with Easy create, do not start over:
   > Modify → **Publicly accessible: Yes**, then add the inbound rule in 1.2.

4. **DB instance size: Sandbox — `db.t4g.micro`.**

   > **Check the price on this page before clicking through.** In the current console
   > Dev/Test is `db.r7g.large` at about **$0.271/hour** — roughly $6.50 a day, $195 a
   > month — and Production is about $1.915/hour. Sandbox is `db.t4g.micro` at about
   > **$0.019/hour**.
   >
   > Sandbox is more than enough. This database holds three products and an orders
   > table, and the bottleneck in this lab is a `LOCK TABLE` on `Inventory`, not CPU or
   > memory. A larger instance buys nothing and bills fourteen times as much.

5. **DB instance identifier:** `webstore-db`
6. **Credentials:** choose **Self managed**, set a master username and password.
   **Write these down** — they go into `products.php` later and the password cannot be
   recovered. (Managed in AWS Secrets Manager is more secure and more correct in
   production, but then `products.php` would have to fetch the secret at runtime, which
   this code does not do.)
7. **Storage:** leave at defaults.
8. **Connectivity → Public access: Yes.**
9. **Database authentication:** password authentication.
10. **Additional configuration → Initial database name:** `Products`

    > Optional. `DatabaseSetup.sql` uses `CREATE DATABASE IF NOT EXISTS`, so it works
    > whether you set this or not.

11. **Create database.** Takes several minutes.

### 1.2 Open the security group — the step the original instructions omit

"Publicly accessible" is necessary but **not sufficient**. The instance still sits
behind a security group that blocks inbound traffic by default. This is the most common
cause of `Connection failed`.

> **Wait for Status: Available first.** While the database still says *Creating* or
> *Backing-up*, it has no endpoint and its security group panel may be empty. This can
> take ten minutes or more. Refresh the Databases list until the status is green.

**Find the security group:**

1. **Aurora and RDS** → **Databases** (left nav) → click **`webstore-db`**.
2. Open the **Connectivity & security** tab.
3. Under **VPC security groups**, click the security group link. **This opens the EC2
   console in a new view** — security groups belong to EC2/VPC, not RDS. Steps 4–6
   happen there.

**Add the rules (now in the EC2 console):**

4. **Inbound rules** tab → **Edit inbound rules** → **Add rule**:
   - Type: **MySQL/Aurora** (TCP 3306)
   - Source: **My IP** — so you can reach it from dbeaver
5. **Add rule** again, Source **Anywhere-IPv4 (0.0.0.0/0)**, so the Beanstalk instances
   can connect.

   > Deliberately permissive to keep the lab simple. In production you would put the
   > database in a private subnet and allow only the application's security group. Worth
   > saying out loud in class — a good contrast with least-privilege practice.

6. **Save rules.**

**Get the endpoint (back in the RDS console):**

7. Go back to **Aurora and RDS** → **Databases** → **`webstore-db`** →
   **Connectivity & security** tab.
8. In the **Endpoint & port** panel, copy the **Endpoint**. It looks like
   `webstore-db.abcd1234efgh.us-east-1.rds.amazonaws.com`.

   This is your `$servername` in `products.php`, and the **Server Host** field in
   dbeaver. Note the **Port** beside it too — 3306 unless you changed it.

   > If the Endpoint field is blank, the instance is not finished provisioning. Wait for
   > Status: Available and refresh.

### 1.3 Load the schema

The repo ships `DatabaseSetup.sql`. **Use it.** It replaces the entire click-through
table-building walkthrough in the original instructions, and is safe to re-run.

```bash
mysql -h <your-rds-endpoint> -u <username> -p < DatabaseSetup.sql
```

Or in dbeaver: connect with the endpoint as Server Host, open `DatabaseSetup.sql`, and
run it with **Execute SCRIPT** (not Execute statement, which runs only the line your
cursor is on).

Verify:

```sql
SELECT * FROM Products.Footwear;      -- expect 3 rows
SELECT ItemID, Count FROM Products.Inventory;
```

> **`Orders.ItemID` must NOT be a primary key.** The original instructions said to make it one.
> That is wrong and it breaks the lab: `Orders` records every purchase, so the same
> `ItemID` appears many times. With a primary key, the first purchase of an item
> succeeds and every subsequent one fails on duplicate key — silently, because the app
> returns HTTP 200 regardless. A whole load test would produce three rows.
> `DatabaseSetup.sql` gets this right. `Inventory.ItemID` as primary key is correct —
> one row per item there.

> **Stock levels.** `DatabaseSetup.sql` seeds 100,000,000 of each item. The original
> 25 + 40 + 15 drains in about twenty seconds under 500 users, and because `burn_cpu()`
> runs *before* the stock check, CPU load keeps looking normal while no inventory
> updates, no table locks, and almost no orders happen for the rest of the run.
>
> - `reset_demo.sql` puts back the original numbers and clears orders — for a live
>   click-through in class.
> - `reset_inventory.sql` puts back the large numbers — run it before **every** load
>   test.

---

## 2. Deploy the app (Elastic Beanstalk)

### 2.1 Prepare the code bundle

1. Edit `Monolithic/products.php` and set the connection details to your RDS values:

   ```php
   $servername = "webstore-db.xxxxxxxx.us-east-1.rds.amazonaws.com";
   $username   = "<your master username>";
   $password   = "<your master password>";
   $database   = "Products";
   $port       = '3306';
   ```

2. Edit `composer.json` and put your own name and email in it.

3. Zip **the four files**, not the folder:

   ```
   products.php   products.js   index.php   composer.json
   ```

   > **Select the files and compress those.** If you zip the *directory*, Beanstalk
   > unpacks a subfolder, finds no application at the root, and serves nothing. This
   > catches almost everyone once.

### 2.2 Create the environment

The console wizard has changed since the original instructions were written. The current pages, in
order, are: **Environment tier → Application information → Environment information →
Platform → Application code → Presets → Configure service access → Review.**

1. AWS Console → **Elastic Beanstalk** → **Create application**.
2. **Environment tier:** Web server environment.
3. **Application name:** `WebStore`. **Environment name:** `webstore-monolith`.
4. **Platform:** PHP. Leave the branch and version at their recommended defaults.
5. **Application code:** Upload your code → upload the zip from 2.1.
6. **Presets: choose High availability, NOT Single instance.**

   > **This is the step that decides whether the lab is possible at all.** *Single
   > instance (free tier)* creates no load balancer and no Auto Scaling group, so the
   > environment physically cannot scale no matter how you set the triggers later —
   > and nothing will tell you why. The original instructions do not mention this page, and
   > Single instance is the tempting choice because it says "free tier."

7. **Configure service access:**
   - **Service role:** create a new one, or use `aws-elasticbeanstalk-service-role`.
   - **EC2 instance profile:** `aws-elasticbeanstalk-ec2-role`.

   > On a brand-new AWS account neither role exists yet and the dropdowns will be
   > empty. Use the "Create role" link to make them before continuing. Students stall
   > here with a permissions error and no obvious cause.

8. Skip the remaining optional pages, **Submit**, and wait. Five to ten minutes.
9. When health goes green, copy the environment **URL**.

### 2.3 Check it works before going further

Open `http://<environment-url>/products.php`. You should see three products.

If the page says `Connection failed`, it is almost always one of: wrong endpoint in
`products.php`, the security group rule from 1.2 missing, or the password mistyped.

---

## 3. Configure autoscaling

This is the subject of Lab 02, and the original instructions did not cover it. **Beanstalk does not
scale on CPU by default** — it scales on outbound network traffic, averaged over five
minutes. Leave the defaults and `burn_cpu()` can peg the CPU for a full fifteen-minute
run while the instance count never moves, with no error anywhere.

Environment → **Configuration** → **Capacity** → **Edit**.

**Instances:**

| Setting | Value |
|---|---|
| Environment type | Load balanced |
| Min instances | 1 |
| Max instances | 4 |

**Scaling triggers:**

Tick **"Manually configure scaling triggers"** to reveal these fields.

| Setting | AWS default | Set to | Why |
|---|---|---|---|
| Metric | `NetworkOut` | **`CPUUtilization`** | `burn_cpu()` makes CPU load, not network load |
| Statistic | Average | Average | — |
| Period | 5 min | **1 min** | At 5, the alarm cannot see a 4-minute load stage |
| Breach duration | 5 min | **1 min** | 5 + 5 means nothing happens for ten minutes |
| Upper threshold | 6000000 | **25** | Percent CPU |
| Scale up increment | 1 | 1 | One instance per firing |
| Lower threshold | 2000000 | **10** | Scale-down trigger |
| Scale down increment | -1 | -1 | — |
| **Scaling cooldown** | **3600 sec** | **60 sec** | See below — this one ruins the demo silently |

> **There is no Unit field in the current console.** Older versions of this form (and
> the `aws:autoscaling:trigger` config namespace the AWS docs describe) have one. The
> console now infers it from the metric — pick `CPUUtilization` and the helper text
> under the threshold boxes changes to read "0-20,000,000 percent". Nothing to set.

> **Scaling cooldown defaults to 3600 seconds — one hour.** After any scaling action,
> Beanstalk refuses to scale again for that long, up *or* down. On a 15-minute load
> test that means one scale-up at roughly the 90-second mark and then nothing at all:
> no second scale-up at the step to 700 users, no scale-down during the cool-down
> phase. Slide 5 of the deck shows the host count rising *and* falling inside the run
> window, which is impossible at 3600.
>
> Set it to **60**. That is aggressive by production standards — real systems use 300+
> to avoid flapping — and saying so in class is worth a sentence: you are tuning for
> visibility inside a class period, not for stability.

Apply, and wait for the environment to finish updating.

> **Use the same thresholds for every environment you compare.** The existing lab deck
> uses 25% on slide 5 and 15% on slide 6, which means those two runs are not comparable
> on instance count — the architecture was not the only thing that differed.

---

## 4. Test

1. Open `http://<environment-url>/products.php`.
2. Set a quantity, enter any email, click **Buy**.
3. Confirm the order landed:

   ```sql
   SELECT * FROM Products.Orders;
   SELECT ItemID, Count FROM Products.Inventory;
   ```

   A new row in `Orders`, and the matching `Inventory.Count` reduced.

4. For the load test:

   ```bash
   mysql -h <endpoint> -u <user> -p < reset_inventory.sql

   locust -f locustfile.py \
     --host http://<environment-url> \
     --headless --csv=runs/mono_readheavy --csv-full-history \
     --html=runs/mono_readheavy.html
   ```

   For the write-heavy run, change only the task weights and the output name:

   ```bash
   VIEW_WEIGHT=1 BUY_WEIGHT=8 locust -f locustfile.py \
     --host http://<environment-url> \
     --headless --csv=runs/mono_writeheavy --csv-full-history \
     --html=runs/mono_writeheavy.html
   ```

   Run Locust from your laptop, never from an EC2 instance inside the environment under
   test — the generator's own CPU would land in the metric the autoscaler triggers on.

5. Capture CloudWatch the same evening: CPUUtilization and HealthyHostCount, cropped to
   the run window. Fine-grained data ages out.

---

## 5. Tear down

Do this the day you finish. It is not optional on a credits-based account.

1. Elastic Beanstalk → environment → **Actions → Terminate environment**. This removes
   the EC2 instances, load balancer, and Auto Scaling group.
2. **RDS → Databases → `webstore-db` → Delete.** RDS does **not** go away with the
   Beanstalk environment and bills until deleted. Decline the final snapshot unless you
   want to keep the data.
3. Check the billing dashboard a day later for anything still running.

---

## Changelog vs. the original instructions

**Corrected — these were wrong:**

| Old | Problem |
|---|---|
| 1.20: `Orders.ItemID` as Primary Key | Breaks the lab. Contradicts `DatabaseSetup.sql`. Only the first purchase of each item is ever recorded, silently. |
| 1.12 + 1.17 both create `Products` | The second fails as already-existing. Now set once, at creation. |
| 1.25: seed Count = 10000 | Contradicted `DatabaseSetup.sql` (25/40/15), and neither survives a 500-user run. Now seeded at 100,000,000; `reset_inventory.sql` restores it. |
| 1.24: "at least two rows" in `Footwear` | The locustfile buys `randint(1, 3)`. Three rows needed. |
| 2.2–2.3: console flow | The wizard has changed. Updated to the current pages. |
| Two steps numbered 2.8 | Renumbered. |
| 1.6: "Choose the Free Tier template" | The size picker is now Production / Dev/Test / Sandbox with prices shown. Dev/Test is `db.r7g.large` at ~$0.271/hr; **Sandbox `db.t4g.micro` at ~$0.019/hr is the right pick** and is easy to click past. |
| 1.3: "Standard create" | The console now labels it **Full configuration**. Also clarified that Easy create is workable — public access is editable after creation — rather than a dead end. |

**Added — these were missing:**

- **Security group inbound rule for 3306.** "Publicly accessible" alone does not admit traffic. Most common cause of `Connection failed`.
- **The Presets page, and the warning not to pick Single instance.** Single instance cannot scale at all.
- **Configure service access.** New page; the IAM roles do not exist on a fresh account.
- **Section 3, autoscaling configuration.** Absent from the original instructions despite being the subject of the lab.
- **Zip the files, not the folder.**
- **Section 5, teardown.** RDS survives environment termination and keeps billing.
- **Free Tier change (July 2025).** New accounts get credits, not the 12-month tier.

**Kept — these were already accurate:**

- Standard create rather than Easy create (1.3)
- Public access = Yes (1.10)
- The dbeaver connection walkthrough (1.14–1.16)
- The CLI schema-loading path — which is the better route, and is now the primary one
- Updating `products.php` with the RDS credentials before zipping (2.7)
- The four files that belong in the zip (2.8)
