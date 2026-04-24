pipeline {
    agent any

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        CLUSTER_NAME = "devsecops-cluster"
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
                sh "trivy fs --scanners vuln,misconfig --format table . || echo 'Trivy continue'"
            }
        }

        stage('🐳 Build Docker') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('📦 Import dans k3d') {
            steps {
                sh "k3d image import ${IMAGE_NAME} -c ${CLUSTER_NAME}"
            }
        }

        stage('🚀 Déploiement Kubernetes') {
            steps {
                sh "kubectl delete deployment php-app-deployment --ignore-not-found"
                sh "kubectl delete service php-service --ignore-not-found"
                sh "kubectl create deployment php-app-deployment --image=${IMAGE_NAME} --port=80"
                sh "kubectl expose deployment php-app-deployment --type=NodePort --port=80 --target-port=80 --name=php-service"
                sh "kubectl scale deployment php-app-deployment --replicas=2"
                sh "kubectl get pods"
            }
        }
    }

    post {
        success {
            echo "✅ Application déployée avec succès sur http://localhost:8888"
        }
        failure {
            echo "❌ Échec du déploiement"
        }
    }
}
