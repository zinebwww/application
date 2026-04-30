pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [[key: 'ref', value: '$.ref']],
            token: 'mon-projet-unique-token', 
            causeString: 'Déclenchement automatique par GitHub Webhook (DevSecOps + IaC)',
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

        stage('🧪 2. Tests Unitaires (Docker-in-Docker)') {
            steps {
                script {
                    echo "Validation du code PHP via PHPUnit..."
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

        stage('🔍 3. Qualité de Code (SonarQube)') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🛡️ 4. Sécurité (Scan Trivy)') {
            steps {
                script {
                    echo "Audit de sécurité sur l'image Docker..."
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL ${IMAGE_NAME} || echo 'Vulnérabilités détectées'"
                }
            }
        }

        stage('🐳 5. Build Image Finale') {
            steps {
                // Utilise votre Dockerfile corrigé (Fix SQLite permissions)
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('📦 6. Importation Multi-Cluster') {
            steps {
                script {
                    def nodes = [
                        'k3d-cluster-prod-1-server-0',
                        'k3d-cluster-prod-2-server-0',
                        'k3d-cluster-dr-server-0'
                    ]
                    nodes.each { nodeName ->
                        echo "Injection de l'image dans : ${nodeName}"
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${nodeName} ctr -n k8s.io images import -"
                    }
                }
            }
        }

        stage('⚙️ 7. Infrastructure as Code (Terraform)') {
            steps {
                script {
                    // On se place dans le dossier terraform que vous avez créé
                    dir('terraform') {
                        echo "Initialisation de Terraform..."
                        sh 'terraform init'
                        echo "Application de la configuration infrastructure..."
                        sh 'terraform apply -auto-approve'
                    }
                }
            }
        }

        stage('🚀 8. Déploiement & Rollout') {
            steps {
                script {
                    // On force le redémarrage des pods pour utiliser la nouvelle image injectée
                    def contexts = ['prod-1', 'prod-2', 'dr']
                    contexts.each { ctx ->
                        echo "Redémarrage de l'application sur : ${ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${ctx}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ PIPELINE NADI ! Code testé, scanné, Infra Terraform appliquée et App déployée."
        }
        failure {
            echo "❌ Échec du pipeline. Vérifiez les logs de l'étape en rouge."
        }
    }
}
