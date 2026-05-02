pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [[key: 'ref', value: '$.ref']],
            token: 'mon-projet-unique-token',
            causeString: 'Déclenchement automatique DevSecOps',
            printPostContent: true,
            silentResponse: false
        )
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }

    stages {
        stage('📥 1. Checkout') {
            steps {
                deleteDir() // Nettoie le dossier avant de commencer
                checkout scm
            }
        }

        stage('🧪 2. Tests PHPUnit') {
            steps {
                script {
                    echo "Validation PHPUnit..."
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

        stage('🔍 3. SonarQube') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    // Utilisation de l'IP de la passerelle Docker pour contacter Sonar
                    sh "${SCANNER_HOME}/bin/sonar-scanner -Dsonar.host.url=http://172.17.0.1:9000"
                }
            }
        }

        stage('🐳 4. Build Image Docker') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan Trivy') {
            steps {
                script {
                    // --light et --skip-db-update pour protéger ton disque et ta RAM
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light --skip-db-update ${IMAGE_NAME} || echo 'Scan terminé'"
                }
            }
        }

        stage('📦 6. Déploiement Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',   node: 'k3d-dr-server-0']
                    ]
                    clusters.each { cluster ->
                        echo "Déploiement sur : ${cluster.ctx}"
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${cluster.node} ctr -n k8s.io images import -"
                        sh "kubectl apply -f k8s-deploy.yaml --context ${cluster.ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${cluster.ctx}"
                    }
                }
            }
        }
    }

    post {
        always {
            script {
                echo "🧹 Nettoyage d'urgence du stockage (Protection des 19 Go)..."
                deleteDir() // Supprime le code source du disque Jenkins
                sh "docker builder prune -f" // Supprime le cache Docker lourd
                sh "docker rmi app-test-image || true" // Supprime l'image de test
                sh "docker image prune -f" // Supprime les images sans nom
            }
        }
        success {
            echo "✅ Pipeline réussi ! Application déployée."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs."
        }
    }
}
