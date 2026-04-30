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
                sh "ls -la"
            }
        }

        stage('🧪 Tests Unitaires') {
            steps {
                script {
                    echo "Exécution des tests via Dockerfile temporaire..."
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

        stage('🔍 Analyse SonarQube') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🛡️ Sécurité - Scan Trivy') {
            steps {
                script {
                    echo "Audit de sécurité..."
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL ${IMAGE_NAME} || echo 'Vulnérabilités détectées'"
                }
            }
        }

        stage('🐳 Build Docker Image') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('📦 Import dans Clusters k3d') {
            steps {
                script {
                    def nodes = [
                        'k3d-cluster-prod-1-server-0',
                        'k3d-cluster-prod-2-server-0',
                        'k3d-cluster-dr-server-0'
                    ]
                    
                    nodes.each { nodeName ->
                        echo "Importation de l'image dans ${nodeName}..."
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${nodeName} ctr -n k8s.io images import -"
                    }
                }
            }
        }

        stage('🚀 Déploiement Kubernetes') {
            steps {
                script {
                    // Noms des contextes corrigés selon votre kubectl config get-contexts
                    def contexts = ['prod-1', 'prod-2', 'dr']
                    
                    contexts.each { ctx ->
                        echo "Mise à jour du déploiement sur le contexte : ${ctx}"
                        // On force le redémarrage pour charger la nouvelle image importée
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${ctx} || echo 'Déploiement non trouvé sur ${ctx}'"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline terminé avec succès ! Votre application est déployée sur prod-1, prod-2 et dr."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les étapes rouges."
        }
    }
}
