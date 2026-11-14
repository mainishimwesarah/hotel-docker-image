# Hotel Management System

A beginner-friendly full-stack hotel management project. Frontend: HTML/CSS/JavaScript served by Nginx. Backend: PHP 8.2 REST API on Apache. Database: MySQL 8. Docker Compose runs the complete application.

## Features
- Dashboard with room, occupancy, guest and reservation statistics
- Add, edit, search and delete hotel rooms
- Register guests
- Create reservations with check-in/check-out dates and automatic price calculation
- Check guests in and out; update room occupancy status
- Cancel reservations that have not checked in

## Requirements
- Docker Desktop installed and running
- PowerShell or Windows Terminal

## Run the project
Open a terminal in this folder and run:

```powershell
docker compose up --build -d
docker compose ps
```

Open http://localhost:8082 in your browser. API health check: http://localhost:8082/api/health

## Stop
```powershell
docker compose down
```
To delete the database volume and all saved hotel data:
```powershell
docker compose down -v
```

## Docker Hub
Create two public repositories, for example `hotel-frontend` and `hotel-backend`. Replace `YOUR_DOCKERHUB_USERNAME`:

```powershell
docker login
docker build -t YOUR_DOCKERHUB_USERNAME/hotel-frontend:1.0 ./frontend
docker build -t YOUR_DOCKERHUB_USERNAME/hotel-backend:1.0 ./backend
docker push YOUR_DOCKERHUB_USERNAME/hotel-frontend:1.0
docker push YOUR_DOCKERHUB_USERNAME/hotel-backend:1.0
```

To pull those images and run the complete application, set your Docker Hub username in PowerShell and run:

```powershell
$env:DOCKERHUB_USERNAME = "YOUR_DOCKERHUB_USERNAME"
docker compose pull
docker compose up -d --no-build
docker compose ps
```

Open http://localhost:8082 in your browser. The username setting applies only to the current PowerShell session.

## Project structure
```text
hotel_management_system/
├── backend/
│   ├── Dockerfile
│   ├── database/init.sql
│   ├── public/.htaccess
│   ├── public/index.php
│   └── src/db.php
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── index.html
│   ├── style.css
│   └── app.js
├── jenkins/
│   ├── create-secrets.py
│   └── README.md
├── k8s/
│   ├── backend.yaml
│   ├── frontend.yaml
│   ├── kustomization.yaml
│   └── mysql.yaml
├── Jenkinsfile
├── docker-compose.yml
└── README.md
```

## Jenkins and Kubernetes deployment

The repository includes a Jenkins pipeline and Kubernetes manifests. The
pipeline builds and pushes the frontend/backend images to Docker Hub, creates
Kubernetes Secrets from Jenkins Credentials, deploys the application and MySQL,
and waits for the workloads to become ready. See [jenkins/README.md](jenkins/README.md)
for Jenkins agent requirements, credential IDs, webhook setup, and deployment
details.

The Kubernetes manifests target a cluster with a default StorageClass and
expose the frontend on NodePort `30080`. The database data is stored in a
PersistentVolumeClaim; normal deployments do not delete it. A local cluster
must be running and its kubeconfig must be usable by the Jenkins agent. For
automatic GitHub push builds, Jenkins also needs a public HTTPS endpoint.

## Notes
- MySQL initializes `init.sql` only when the database volume is first created. If you need to reinitialize after changing it, run `docker compose down -v` then `docker compose up --build -d`. This deletes existing data.
- This is a classroom/demo project. Before real hotel use, add login and role permissions, stronger validation, payment integration, backups, audit logs and production secret management.
- Demo room and guest records are inserted automatically on first database initialization.
Testing automatic Jenkins build
Testing automatic Jenkins trigger
