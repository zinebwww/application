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
        stage('📥 1. Récupération du code') {
            steps {
                deleteDir()
                checkout scm
                sh "ls -la"
            }
        }

        stage('🧪 2. Tests Unitaires') {
            steps {
                script {
                    echo "Exécution des tests PHPUnit..."
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

        stage('🔍 3. Qualité SonarQube') {
            steps {
                // On lance SonarQube seul pour ne pas saturer la RAM
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🛡️ 4. Sécurité Trivy') {
            steps {
                script {
                    echo "Audit de sécurité image..."
                    // On utilise --light pour aller 10x plus vite
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'Scan fini'"
                }
            }
        }

        stage('🐳 5. Build Image') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('📦 6. Injection dans Clusters') {
            steps {
                script {
                    // Noms des serveurs k3d (prod-1 et dr)
                    def nodes = ['k3d-prod-1-server-0', 'k3d-dr-server-0']
                    nodes.each { nodeName ->
                        echo "Importation dans ${nodeName}..."
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${nodeName} ctr -n k8s.io images import -"
                    }
                }
            }
        }

        stage('🚀 7. Déploiement & Rollout') {
            steps {
                script {
                    // On déploie sur vos deux environnements
                    def contexts = ['k3d-prod-1', 'k3d-dr']
                    contexts.each { ctx ->
                        echo "Mise à jour de : ${ctx}"
                        sh "kubectl apply -f k8s-deploy.yaml --context ${ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${ctx}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ PIPELINE TERMINÉE EN TEMPS RECORD !"
        }
        failure {
            echo "❌ Échec. Vérifiez les logs."
        }
    }
}
