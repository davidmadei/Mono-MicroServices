# Deploy the Microservices Webstore to AWS (PaaS)

Same database, same Beanstalk steps as the monolith — do `Monolithic/readMePaaS.md`
first. This page covers only what is different.

## 1. Database

Reuse the `webstore-db` RDS instance from the monolith if it is still running.
Otherwise follow Section 1 of `Monolithic/readMePaaS.md`.

## 2. Deploy three environments, in this order

The app is split into three services. Each one is its own Beanstalk environment, with
its own URL, load balancer and Auto Scaling group.

| Order | Environment | Files to zip | Edit before zipping |
|---|---|---|---|
| 1 | BuyProducts | `buyProducts.php`, `index.php`, `composer.json` | RDS endpoint, username, password |
| 2 | DisplayProducts | `displayProducts.php`, `index.php`, `composer.json` | RDS endpoint, username, password |
| 3 | RouteRequest | `products.php`, `products.js`, `index.php`, `composer.json` | The two service URLs from steps 1 and 2 |

RouteRequest goes last because it needs the other two URLs. In `products.php`:

```php
$displayProductsMicroService = "http://<display-products-env-url>/displayProducts.php";
$buyProductsMicroService     = "http://<buy-products-env-url>/buyProducts.php";
```

Zip the files, not the folder. Choose **High availability**, not Single instance, on
the Presets page — same as the monolith.

## 3. Autoscaling — on all three environments

Beanstalk does not scale on CPU by default. Apply the settings in Section 3 of
`Monolithic/readMePaaS.md` to **each** of the three environments, with the same
thresholds as the monolith. If one environment is left on the defaults it will never
scale, and nothing will tell you.

## 4. Check it works

1. Open `http://<routerequest-env-url>/products.php`. Three products should show.
2. Buy something, then confirm in dbeaver that a row landed in `Orders` **with the
   email filled in**.

If the page says *RouteRequest could not reach DisplayProducts* (or *BuyProducts*), the
URL in `products.php` is wrong or that environment is not healthy yet.

## 5. Load test

Point Locust at **RouteRequest only**. DisplayProducts and BuyProducts are called by
RouteRequest from inside AWS; users (and Locust) never reach them directly.

```bash
mysql -h <endpoint> -u <user> -p < reset_inventory.sql

locust -f locustfile.py \
  --host http://<routerequest-env-url> \
  --headless --csv=runs/micro_readheavy --csv-full-history \
  --html=runs/micro_readheavy.html
```

For the write-heavy run, prefix with `VIEW_WEIGHT=1 BUY_WEIGHT=8` and change the output
name to `micro_writeheavy`.

In CloudWatch, capture CPUUtilization and HealthyHostCount for **all three**
environments — that is where you see which service actually scaled.

## 6. Tear down

Terminate all three environments, then delete the RDS instance. See Section 5 of
`Monolithic/readMePaaS.md`.
