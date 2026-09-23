# Lab 02 — Monolithic vs. Microservices on AWS

The same webstore built two ways, deployed to AWS Elastic Beanstalk with an Amazon RDS
database, then load-tested with Locust to see how each one scales.

By the end of this lab, a student can:

- Understand the difference between Monolithic & Microservices
- See how CPU utilization performs in each of these architecture models
- Describe the behavior of each of these services

## Where to start

1. `Monolithic/readMePaaS.md` — database, first deployment, autoscaling setup.
2. `Microservices/readMePaas.md` — the same app split into three services.

Everything runs in the cloud. You need an AWS account, dbeaver (or a `mysql` client),
and Locust (`pip install locust`). No local web server, no Docker.

## What's here

| File | What it is |
|---|---|
| `Monolithic/` | The whole app in one `products.php` |
| `Microservices/` | RouteRequest (`products.php`), DisplayProducts, BuyProducts |
| `DatabaseSetup.sql` | Creates the tables and seed data. Safe to re-run |
| `reset_inventory.sql` | Run before **every** load test |
| `reset_demo.sql` | Original stock numbers and empty orders, for a live click-through |
| `locustfile.py` | The load test. Point it at the monolith or at RouteRequest only |
| `readMeLocal.md`, `setup.sh`, `setup-mac.sh` | Optional: run the app on your own machine to read the code |
| `*/readMeIaaS.md` | The older EC2 (IaaS) version of the deployment, for reference |

## Two things that silently break the lab

- **Beanstalk does not scale on CPU by default.** It scales on network traffic. Set the
  trigger to `CPUUtilization` on every environment (Section 3 of
  `Monolithic/readMePaaS.md`), or the host count never moves.
- **Database credentials go in the PHP files before you zip them.** They ship as
  placeholders like `<your-rds-endpoint>`. Do not commit your real values.
