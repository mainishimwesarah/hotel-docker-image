pipeline {
    agent {
        label 'docker-kubectl'
    }

    options {
        disableConcurrentBuilds()
        skipDefaultCheckout(true)
        timestamps()
    }

    triggers {
        githubPush()
    }

    environment {
        K8S_NAMESPACE = 'hotel-management'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build and push images') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: 'dockerhub-credentials',
                        usernameVariable: 'DOCKERHUB_USER',
                        passwordVariable: 'DOCKERHUB_TOKEN'
                    )
                ]) {
                    sh '''
                        set +x
                        printf '%s' "$DOCKERHUB_TOKEN" | docker login --username "$DOCKERHUB_USER" --password-stdin
                        docker build --pull -t "$DOCKERHUB_USER/hotel-backend:$BUILD_NUMBER" -t "$DOCKERHUB_USER/hotel-backend:latest" ./backend
                        docker build --pull -t "$DOCKERHUB_USER/hotel-frontend:$BUILD_NUMBER" -t "$DOCKERHUB_USER/hotel-frontend:latest" ./frontend
                        docker push "$DOCKERHUB_USER/hotel-backend:$BUILD_NUMBER"
                        docker push "$DOCKERHUB_USER/hotel-backend:latest"
                        docker push "$DOCKERHUB_USER/hotel-frontend:$BUILD_NUMBER"
                        docker push "$DOCKERHUB_USER/hotel-frontend:latest"
                        docker logout
                    '''
                }
            }
        }

        stage('Deploy to Kubernetes') {
            steps {
                withCredentials([
                    file(credentialsId: 'hotel-kubeconfig', variable: 'KUBECONFIG'),
                    usernamePassword(
                        credentialsId: 'hotel-db-app',
                        usernameVariable: 'HOTEL_DB_USER',
                        passwordVariable: 'HOTEL_DB_PASSWORD'
                    ),
                    string(credentialsId: 'hotel-db-root-password', variable: 'MYSQL_ROOT_PASSWORD'),
                    usernamePassword(
                        credentialsId: 'dockerhub-credentials',
                        usernameVariable: 'DOCKERHUB_USER',
                        passwordVariable: 'DOCKERHUB_TOKEN'
                    )
                ]) {
                    sh '''
                        set +x
                        kubectl create namespace "$K8S_NAMESPACE" --dry-run=client -o yaml | kubectl apply -f -

                        python3 jenkins/create-secrets.py | kubectl apply -f -
                        kubectl create configmap hotel-db-init \
                            --from-file=db-init.sql=backend/database/init.sql \
                            --namespace "$K8S_NAMESPACE" \
                            --dry-run=client -o yaml | kubectl apply -f -
                        kubectl apply -k k8s
                        kubectl set image deployment/hotel-backend \
                            backend="docker.io/$DOCKERHUB_USER/hotel-backend:$BUILD_NUMBER" \
                            --namespace "$K8S_NAMESPACE"
                        kubectl set image deployment/hotel-frontend \
                            frontend="docker.io/$DOCKERHUB_USER/hotel-frontend:$BUILD_NUMBER" \
                            --namespace "$K8S_NAMESPACE"
                        kubectl rollout status deployment/hotel-db --namespace "$K8S_NAMESPACE" --timeout=180s
                        kubectl rollout status deployment/hotel-backend --namespace "$K8S_NAMESPACE" --timeout=180s
                        kubectl rollout status deployment/hotel-frontend --namespace "$K8S_NAMESPACE" --timeout=180s
                    '''
                }
            }
        }
    }

    post {
        always {
            sh 'docker logout >/dev/null 2>&1 || true'
        }
    }
}
