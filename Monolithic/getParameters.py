from ssm_parameter_store import EC2ParameterStore

store = EC2ParameterStore(region_name="us-east-1",
    aws_access_key_id="<access-key-id>",
    aws_secret_access_key="<access-key>"
)

print(store.get_parameter("WebstoreDatabaseServer")["WebstoreDatabaseServer"])
