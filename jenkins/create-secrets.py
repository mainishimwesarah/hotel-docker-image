import base64
import json
import os
import sys


def required(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        raise SystemExit(f"Required environment variable {name} is not set")
    return value


def make_secret(name: str, secret_type: str, values: dict[str, str]) -> dict:
    return {
        "apiVersion": "v1",
        "kind": "Secret",
        "metadata": {
            "name": name,
            "namespace": required("K8S_NAMESPACE"),
        },
        "type": secret_type,
        "data": {
            key: base64.b64encode(value.encode()).decode()
            for key, value in values.items()
        },
    }


def main() -> None:
    docker_user = required("DOCKERHUB_USER")
    docker_token = required("DOCKERHUB_TOKEN")
    docker_auth = base64.b64encode(
        f"{docker_user}:{docker_token}".encode()
    ).decode()
    registry_config = json.dumps({
        "auths": {
            "https://index.docker.io/v1/": {
                "username": docker_user,
                "password": docker_token,
                "auth": docker_auth,
            }
        }
    })

    manifests = [
        make_secret("hotel-db-credentials", "Opaque", {
            "MYSQL_ROOT_PASSWORD": required("MYSQL_ROOT_PASSWORD"),
            "MYSQL_USER": required("HOTEL_DB_USER"),
            "MYSQL_PASSWORD": required("HOTEL_DB_PASSWORD"),
        }),
        make_secret("hotel-registry", "kubernetes.io/dockerconfigjson", {
            ".dockerconfigjson": registry_config,
        }),
    ]
    for manifest in manifests:
        json.dump(manifest, sys.stdout)
        sys.stdout.write("\n---\n")


if __name__ == "__main__":
    main()
