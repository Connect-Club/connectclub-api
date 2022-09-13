# Postgres for prod

create ssd Storage class

```bash
kubectl apply ssd-sc.yaml
```

use helm to install postgresql

```bash
helm upgrade -i postgres-prod stable/postgresql --version=8.4.1 -f postgres.yaml
```

Example [values](postgres.yaml) for production postgresql

## external access

create k8s loadbalancer:

```bash
kubectl apply -f postgres-lb.yaml
```

check it:

```bash
kubectl describe svc postgres-prod-external
```

and connect to __LoadBalancer Ingress__ IP

# build composer image

```bash
docker build composer --target=composer -f php.Dockerfile .
```
