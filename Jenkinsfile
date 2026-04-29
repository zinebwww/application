pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [[key: 'ref', value: '$.ref']],
            token: 'mon-projet-unique-token', 
            causeString: 'Déclenchement automatique par GitHub Webhook',
            printPostContent: true,
            silentResponse: false
        )
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }

    stages {
        stage('📥 Récupération du code') {
            steps {
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 Tests Unitaires') {
            steps {
                script {
                    echo "Installation des dépendances et exécution des tests..."
                    sh """
                        docker run --rm -v $(pwd):/app -w /app composer install
                        docker run --rm -v $(pwd):/app -w /app php:8.2-cli ./vendor/bin/phpunit tests
                    """
                }
            }
        }

        stage('🔍 Analyse SonarQube') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "/bin/sonar-scanner"
                }
            }
        }

        stage('🛡️ Sécurité - Scan Trivy') {
            steps {
                script {
                    echo "Audit de sécurité en cours..."
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL  || echo 'Vulnérabilités détectées'"
                }
            }
        }

        stage('🐳 Build Docker Image') {
            steps {
                sh "docker build -t  ."
            }
        }

        stage('📦 Import dans Clusters k3d') {
            steps {
                script {
                    def nodes = ['k3d-cluster-prod-1-server-0', 'k3d-cluster-prod-2-server-0', 'k3d-cluster-dr-server-0']
                    nodes.each { nodeName ->
                        echo "Importation dans ..."
                        sh "docker save  | docker exec -i  ctr -n k8s.io images import -"
                    }
                }
            }
        }

        stage('🚀 Déploiement Kubernetes') {
            steps {
                script {
                    def contexts = ['cluster-prod-1', 'cluster-prod-2', 'cluster-dr']
                    contexts.each { ctx ->
                        sh "kubectl rollout restart deployment absence-app-deploy --context  || echo 'Déploiement non trouvé sur '"
                    }
                }
            }
        }
    }

    post {
        success { echo "✅ Pipeline terminé avec succès !" }
        failure { echo "❌ Échec du pipeline." }
    }
}
