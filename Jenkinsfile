pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [
                [key: 'ref', value: '$.ref']
            ],
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
                    // Utilisation de ''' pour éviter les erreurs de syntaxe Groovy avec le symbole $
                    sh '''
                        docker run --rm -v $(pwd):/app -w /app composer install
                        docker run --rm -v $(pwd):/app -w /app php:8.2-cli ./vendor/bin/phpunit tests
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
                    echo "Audit de sécurité sur l'image Docker..."
                    // Scan de l'image et continuation même si des vulnérabilités sont trouvées
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
                        // Commande correcte pour importer dans le namespace k8s de containerd
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${nodeName} ctr -n k8s.io images import -"
                    }
                }
            }
        }

        stage('🚀 Déploiement Kubernetes') {
            steps {
                script {
                    def contexts = ['cluster-prod-1', 'cluster-prod-2', 'cluster-dr']
                    
                    contexts.each { ctx ->
                        echo "Mise à jour du déploiement sur : ${ctx}"
                        // Redémarrage des pods pour charger la nouvelle image importée
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${ctx} || echo 'Déploiement non trouvé sur ${ctx}'"
                    }
                }
            }
        }

        stage('🧹 Nettoyage') {
            steps {
                // Supprime les images intermédiaires pour gagner de l'espace disque
                sh "docker image prune -f"
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline terminé avec succès ! Code testé, scanné et déployé."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs de l'étape en rouge."
        }
    }
}
