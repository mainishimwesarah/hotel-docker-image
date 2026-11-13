# Jenkins setup

The repository `Jenkinsfile` builds and pushes the frontend and backend images,
creates/updates Kubernetes Secrets from Jenkins Credentials, applies `k8s/`,
and waits for the database and application rollouts.

## Jenkins and agent requirements

- Jenkins with the Pipeline, Git, GitHub, and Credentials Binding plugins.
- A trusted Linux Jenkins agent labeled `docker-kubectl` with Docker CLI/daemon
  access, `kubectl`, Python 3, and network access to Docker Hub and the cluster.
- A Kubernetes context reachable from that agent. For local Kubernetes,
  Jenkins must run where the selected cluster context is usable; a kubeconfig
  that points at the developer's `localhost` will not work from a remote agent.
- A GitHub repository containing this project and a Pipeline or Multibranch
  Pipeline job configured to load its `Jenkinsfile`.

Docker daemon access grants the agent powerful control over its host. Use a
dedicated, trusted agent; do not run untrusted pull-request jobs on it.

## Jenkins Credentials

Add these credentials under **Manage Jenkins → Credentials**. IDs must match:

| ID | Type | Contents |
| --- | --- | --- |
| `dockerhub-credentials` | Username with password | Docker Hub username and access token |
| `hotel-kubeconfig` | Secret file | Kubeconfig for the target cluster |
| `hotel-db-app` | Username with password | MySQL application username and password |
| `hotel-db-root-password` | Secret text | MySQL root password |

Use unique, strong database passwords. Do not commit credentials or paste them
into the Jenkinsfile. The pipeline applies them as Kubernetes Secrets in the
`hotel-management` namespace. Restrict Jenkins credential access and Kubernetes
RBAC permissions to the deployment job and namespace.

The two Docker Hub repositories must be named `hotel-backend` and
`hotel-frontend` under the username in `dockerhub-credentials`. They may be
private; the pipeline also creates the image-pull secret used by the workloads.

## GitHub webhook

For push-triggered builds, configure the GitHub repository webhook as:

- Payload URL: `https://<public-jenkins-host>/github-webhook/`
- Content type: `application/json`
- Event: **Just the push event**

Jenkins must be reachable by GitHub over HTTPS. A local-only Jenkins instance
cannot receive GitHub webhooks without a secure, publicly reachable endpoint.
Never expose an unauthenticated Jenkins controller directly to the internet.

## Deployment behavior

Each build publishes immutable `BUILD_NUMBER` image tags and also updates
`latest`; Kubernetes deployments use the immutable build tags. MySQL data is
stored in the `hotel-db-data` PersistentVolumeClaim. The SQL initialization
script runs only when MySQL initializes an empty data directory; applying a
new image or manifest does not reset that volume.
The database user/password stored in Jenkins must match the credentials already
initialized in MySQL. Changing Jenkins credentials alone does not rotate
passwords inside an existing database or change its root password.

The frontend is exposed through NodePort `30080`. Use
`http://<node-address>:30080`; access from the host depends on the local cluster
provider. The MySQL Service is internal to the namespace and is not exposed
outside the cluster.

This stack is a deployment starting point, not a production-hardened hotel
system. Configure TLS/Ingress, backups, monitoring, network policies, resource
capacity, and managed database operations before production use. The app does
not implement user authentication or authorization.
