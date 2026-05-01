pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [[key: 'ref', value: '$.ref']],
            token: 'mon-projet-unique-token',
            causeString: 'Déclenchement automatique par GitHub Webhook (DevSecOps)',
            printPostContent: true,
            silentResponse: false
        )
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }

    stages {
        stage('📥 1. Récupération du code (Checkout)') {
            steps {
                deleteDir()
                checkout scm
                sh 'ls -la'
            }
        }

        stage('🧪 2. Tests unitaires PHPUnit') {
            steps {
                script {
                    echo "Exécution des tests unitaires via Docker..."
                    sh '''
                        echo "FROM composer:latest
                        COPY . /app
                        WORKDIR /app
                        RUN composer install
                        ENTRYPOINT [\\"./vendor/bin/phpunit\\", \\"tests\\"]" > Dockerfile.test
                        docker build -t app-test-image -f Dockerfile.test .
                        docker run --rm app-test-image
                    '''
                }
            }
        }

        stage('🔍 3. Analyse de code (SonarQube)') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🐳 4. Construction de l\'image Docker') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan de sécurité (Trivy)') {
            steps {
                script {
                    // Analyse des vulnérabilités HIGH et CRITICAL, mode light pour économiser RAM
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'Scan terminé'"
                }
            }
        }

        stage('📦 6. Déploiement multi-cluster (k3d)') {
            steps {
                script {
                    // Définition des clusters (nom du contexte kubectl et nom du nœud serveur)
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',   node: 'k3d-dr-server-0']
                    ]
                    clusters.each { cluster ->
                        echo "Déploiement sur le cluster : ${cluster.ctx}"
                        // Injection de l'image Docker dans le cluster k3d
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${cluster.node} ctr -n k8s.io images import -"
                        // Application du manifeste Kubernetes et redémarrage du déploiement
                        sh "kubectl apply -f k8s-deploy.yaml --context ${cluster.ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${cluster.ctx}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline exécuté avec succès ! Application déployée sur les deux clusters."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs ci-dessus."
        }
    }
}
