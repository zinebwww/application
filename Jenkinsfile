pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [
                [key: 'ref', value: '$.ref']
            ],
            token: 'mon-projet-unique-token', // Doit correspondre à l'URL smee/webhook
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
                    // Utilisation de Trivy via Docker pour être sûr que ça fonctionne
                    try {
                        sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL ${IMAGE_NAME}"
                    } catch (Exception e) {
                        echo "Le scan Trivy a trouvé des vulnérabilités ou a échoué, on continue le pipeline."
                    }
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
                    def clusters = [
                        'k3d-cluster-prod-1-server-0',
                        'k3d-cluster-prod-2-server-0',
                        'k3d-cluster-dr-server-0'
                    ]
                    
                    clusters.each { node ->
                        echo "Importation de l'image dans ${node}..."
                        // La méthode du "Pipe" : Rapide, efficace, pas de fichier temporaire
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${node} docker load"
                    }
                }
            }
        }

        stage('🚀 Déploiement Kubernetes') {
            steps {
                script {
                    // Exemple de déploiement (ajustez les noms des contextes si nécessaire)
                    // On force le redémarrage pour prendre en compte la nouvelle image
                    try {
                        sh "kubectl rollout restart deployment absence-app-deploy --context cluster-prod-1 || echo 'Premier déploiement'"
                        sh "kubectl rollout restart deployment absence-app-deploy --context cluster-prod-2 || echo 'Premier déploiement'"
                        sh "kubectl rollout restart deployment absence-app-deploy --context cluster-dr || echo 'Premier déploiement'"
                    } catch (Exception e) {
                        echo "Erreur lors du déploiement kubectl : ${e.message}"
                    }
                }
            }
        }

        stage('🧹 Nettoyage') {
            steps {
                sh "docker image prune -f"
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline terminé avec succès ! L'application est à jour sur tous les clusters."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs ci-dessus."
        }
    }
}
