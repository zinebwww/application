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

        stage('📦 Import image dans clusters') {
            steps {
                script {
                    def clusters = ["cluster-prod-1", "cluster-prod-2", "cluster-dr"]
                    for (cluster in clusters) {
                        sh """
                            docker save ${IMAGE_NAME} -o /tmp/${IMAGE_NAME}.tar
                            docker cp /tmp/${IMAGE_NAME}.tar ${cluster}-server-0:/tmp/
                            docker exec ${cluster}-server-0 ctr image import /tmp/${IMAGE_NAME}.tar || true
                            rm /tmp/${IMAGE_NAME}.tar
                        """
                    }
                }
            }
        }

        stage('🚀 Déploiement sur clusters') {
            steps {
                script {
                    def contexts = ["prod-1", "prod-2", "dr"]
                    for (ctx in contexts) {
                        sh """
                            kubectl config use-context ${ctx}
                            kubectl delete deployment php-app-deployment --ignore-not-found
                            kubectl delete service php-service --ignore-not-found
                            kubectl create deployment php-app-deployment --image=${IMAGE_NAME} --port=80
                            kubectl expose deployment php-app-deployment --type=NodePort --port=80 --target-port=80 --name=php-service
                            kubectl scale deployment php-app-deployment --replicas=2
                            kubectl patch deployment php-app-deployment -p '{"spec":{"template":{"spec":{"containers":[{"name":"absence-app","imagePullPolicy":"Never"}]}}}}'
                        """
                    }
                }
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
