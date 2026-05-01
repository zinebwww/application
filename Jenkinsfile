pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [[key: 'ref', value: '$.ref']],
            token: 'mon-projet-unique-token', 
            causeString: 'Déclenchement automatique par GitHub Webhook (IaC + DevSecOps)',
            printPostContent: true,
            silentResponse: false
        )
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }

    stages {
        stage('📥 1. Code & Infrastructure') {
            steps {
                deleteDir()
                checkout scm
                script {
                    echo "Vérification de l'infrastructure via Terraform..."
                    dir('infrastructure') {
                        // Utilise -reconfigure pour s'adapter au redémarrage de la Kali
                        sh 'terraform init -reconfigure'
                        sh 'terraform apply -auto-approve'
                    }
                }
            }
        }

        stage('🧪 2. Tests Unitaires') {
            steps {
                script {
                    echo "Exécution des tests PHPUnit via Docker..."
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

        stage('🔍 3. Analyse SonarQube') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🛡️ 4. Sécurité (Trivy)') {
            steps {
                script {
                    echo "Audit de sécurité sur l'image Docker..."
                    // On utilise --light pour économiser la RAM
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'Scan terminé'"
                }
            }
        }

        stage('🐳 5. Build Image Finale') {
            steps {
                // Utilise le Dockerfile avec le fix SQLite permissions
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('📦 6. Injection dans Clusters') {
            steps {
                script {
                    // Noms des noeuds k3d correspondants à vos clusters
                    def nodes = ['k3d-prod-1-server-0', 'k3d-dr-server-0']
                    
                    nodes.each { nodeName ->
                        echo "Injection de l'image dans le noeud : ${nodeName}"
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${nodeName} ctr -n k8s.io images import -"
                    }
                }
            }
        }

        stage('🚀 7. Déploiement Kubernetes') {
            steps {
                script {
                    // CONTEXTES CORRIGÉS : k3d-prod-1 et k3d-dr
                    def contexts = ['k3d-prod-1', 'k3d-dr']
                    
                    contexts.each { ctx ->
                        echo "Mise à jour du déploiement sur le contexte : ${ctx}"
                        // On applique le fichier de déploiement et on force le redémarrage
                        sh "kubectl apply -f k8s-deploy.yaml --context ${ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${ctx}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ PIPELINE TERMINÉE AVEC SUCCÈS (Prod-1 & DR à jour)"
        }
        failure {
            echo "❌ ÉCHEC DU PIPELINE. Vérifiez l'étape en rouge."
        }
    }
}
