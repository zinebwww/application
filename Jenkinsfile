pipeline {
    agent any

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }

    stages {
        stage('📥 Récupération du code') {
            steps {
                deleteDir()
                git branch: 'main', url: 'https://github.com/zinebwww/application.git'
                sh 'ls -la'
            }
        }

        stage('🔍 SonarQube') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🛡️ Trivy') {
            steps {
                script {
                    try {
                        timeout(time: 2, unit: 'MINUTES') {
                            sh "trivy fs --skip-db-update --scanners vuln,misconfig --format table . || echo 'Trivy non dispo'"
                        }
                    } catch (err) {
                        echo "Trivy ignoré"
                    }
                }
            }
        }

        stage('🐳 Build Docker') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('📦 Export image Docker') {
            steps {
                sh "docker save ${IMAGE_NAME} -o /tmp/absence-app.tar"
            }
        }

        stage('📦 Import dans cluster-prod-1') {
            steps {
                sh """
                    docker cp /tmp/absence-app.tar k3d-cluster-prod-1-server-0:/tmp/
                    docker exec k3d-cluster-prod-1-server-0 ctr image import /tmp/absence-app.tar || true
                """
            }
        }

        stage('📦 Import dans cluster-prod-2') {
            steps {
                sh """
                    docker cp /tmp/absence-app.tar k3d-cluster-prod-2-server-0:/tmp/
                    docker exec k3d-cluster-prod-2-server-0 ctr image import /tmp/absence-app.tar || true
                """
            }
        }

        stage('📦 Import dans cluster-dr') {
            steps {
                sh """
                    docker cp /tmp/absence-app.tar k3d-cluster-dr-server-0:/tmp/
                    docker exec k3d-cluster-dr-server-0 ctr image import /tmp/absence-app.tar || true
                """
            }
        }

        stage('🚀 Déploiement sur prod-1') {
            steps {
                sh """
                    kubectl config use-context prod-1
                    kubectl delete deployment php-app-deployment --ignore-not-found
                    kubectl delete service php-service --ignore-not-found
                    kubectl create deployment php-app-deployment --image=${IMAGE_NAME} --port=80
                    kubectl expose deployment php-app-deployment --type=NodePort --port=80 --target-port=80 --name=php-service
                    kubectl scale deployment php-app-deployment --replicas=2
                    kubectl patch deployment php-app-deployment -p '{"spec":{"template":{"spec":{"containers":[{"name":"absence-app","imagePullPolicy":"Never"}]}}}}'
                """
            }
        }

        stage('🚀 Déploiement sur prod-2') {
            steps {
                sh """
                    kubectl config use-context prod-2
                    kubectl delete deployment php-app-deployment --ignore-not-found
                    kubectl delete service php-service --ignore-not-found
                    kubectl create deployment php-app-deployment --image=${IMAGE_NAME} --port=80
                    kubectl expose deployment php-app-deployment --type=NodePort --port=80 --target-port=80 --name=php-service
                    kubectl scale deployment php-app-deployment --replicas=2
                    kubectl patch deployment php-app-deployment -p '{"spec":{"template":{"spec":{"containers":[{"name":"absence-app","imagePullPolicy":"Never"}]}}}}'
                """
            }
        }

        stage('🚀 Déploiement sur dr') {
            steps {
                sh """
                    kubectl config use-context dr
                    kubectl delete deployment php-app-deployment --ignore-not-found
                    kubectl delete service php-service --ignore-not-found
                    kubectl create deployment php-app-deployment --image=${IMAGE_NAME} --port=80
                    kubectl expose deployment php-app-deployment --type=NodePort --port=80 --target-port=80 --name=php-service
                    kubectl scale deployment php-app-deployment --replicas=2
                    kubectl patch deployment php-app-deployment -p '{"spec":{"template":{"spec":{"containers":[{"name":"absence-app","imagePullPolicy":"Never"}]}}}}'
                """
            }
        }

        stage('🧹 Nettoyage') {
            steps {
                sh "rm -f /tmp/absence-app.tar"
            }
        }
    }

    post {
        success {
            echo "✅ Déploiement réussi sur les 3 clusters !"
            echo "   - prod-1: http://localhost:8081"
            echo "   - prod-2: http://localhost:8082"
            echo "   - dr: http://localhost:8083"
        }
        failure {
            echo "❌ Échec du pipeline"
        }
    }
}
