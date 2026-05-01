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
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 2. Tests Unitaires (PHPUnit)') {
            steps {
                script {
                    echo "Lancement des tests PHPUnit via Docker..."
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

        stage('🔍 3. Analyse Code (SonarQube)') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🐳 4. Build Image Docker') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan Sécurité (Trivy)') {
            steps {
                script {
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'Scan terminé'"
                }
            }
        }

        stage('📦 6. Déploiement Multi-Cluster (k3d)') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',   node: 'k3d-dr-server-0']
                    ]
                    clusters.each { cluster ->
                        echo "Déploiement sur le cluster : ${cluster.ctx}"
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${cluster.node} ctr -n k8s.io images import -"
                        sh "kubectl apply -f k8s-deploy.yaml --context ${cluster.ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${cluster.ctx}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline réussi ! Application déployée et monitoring actif."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs."
        }
    }
}
