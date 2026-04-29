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
                    echo "Lancement du scan de sécurité sur l'image..."
                    // On utilise l'image Docker de Trivy pour scanner l'image qu'on va builder
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL ${IMAGE_NAME} || echo 'Des vulnérabilités ont été détectées'"
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
                        echo "Importation de l'image dans le noeud : ${nodeName}..."
                        // Commande spécifique pour k3d : on utilise k3s ctr pour importer le flux
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${nodeName} k3s ctr images import -"
                    }
                }
            }
        }

        stage('🚀 Déploiement Kubernetes') {
            steps {
                script {
                    // Liste des contextes Kubernetes (assurez-vous qu'ils existent via kubectl config get-contexts)
                    def contexts = ['cluster-prod-1', 'cluster-prod-2', 'cluster-dr']
                    
                    contexts.each { ctx ->
                        echo "Mise à jour du déploiement sur le contexte : ${ctx}"
                        // On force le redémarrage pour charger la nouvelle image importée
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${ctx} || echo 'Le déploiement n existe pas encore sur ${ctx}'"
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
            echo "✅ Succès total ! Webhook -> Jenkins -> Sonar -> Trivy -> k3d"
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs."
        }
    }
}
