from datetime import datetime, timedelta
import boto3

metric_namespace = 'webstore'
metric_name = 'page-view-count'
now = datetime.utcnow()
resource = boto3.resource('cloudwatch')
metric = resource.Metric(metric_namespace, metric_name)
metric.put_data(Namespace=metric_namespace,
                MetricData=[{
                    'MetricName': metric_name,
                    'Value': 1
                }])
