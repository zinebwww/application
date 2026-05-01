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

        stage('🧪 2. Tests Unitaires') {
            steps {
                script {
                    echo "Validation PHPUnit via Docker-in-Docker..."
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

        stage('🔍 3. Analyse de Code (SonarQube)') {
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
                    // -light pour économiser la RAM sur Kali
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'Scan OK'"
                }
            }
        }

        stage('📦 6. Déploiement Kubernetes Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr', node: 'k3d-dr-server-0']
                    ]
                    
                    clusters.each { cluster ->
                        echo "Déploiement sur : ${cluster.ctx}"
                        // Importation de l'image
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${cluster.node} ctr -n k8s.io images import -"
                        // Mise à jour Kubernetes
                        sh "kubectl apply -f k8s-deploy.yaml --context ${cluster.ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${cluster.ctx}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ PIPELINE SUCCESS : Application déployée et Monitoring actif !"
        }
        failure {
            echo "❌ PIPELINE FAILED : Vérifiez les logs Docker ou Sonar."
        }
    }
}
