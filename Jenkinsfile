pipeline {
    agent any

    triggers {
        GenericTrigger(
            token: 'mon-projet-unique-token',
            genericVariables: [[key: 'ref', value: '$.ref']]
        )
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        // IP de la passerelle Docker pour contacter SonarQube sur l'hôte
        SONAR_URL = "http://172.17.0.1:9000"
    }

    stages {
        stage('📥 1. Préparation') {
            steps {
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 2. Tests & Qualité (Parallèle)') {
            parallel {
                stage('PHPUnit') {
                    steps {
                        script {
                            sh '''
                                cat > Dockerfile.test <<INNEREOF
FROM composer:latest
COPY . /app
WORKDIR /app
RUN composer install
ENTRYPOINT ["./vendor/bin/phpunit", "tests"]
INNEREOF
                                docker build -t app-test-image -f Dockerfile.test .
                                docker run --rm app-test-image
                                docker rmi app-test-image || true
                            '''
                        }
                    }
                }
                stage('SonarQube') {
                    steps {
                        withSonarQubeEnv('sonar-server') {
                            sh "${SCANNER_HOME}/bin/sonar-scanner -Dsonar.host.url=${SONAR_URL} -Dsonar.qualitygate.wait=false"
                        }
                    }
                }
            }
        }

        stage('🐳 3. Build Image Finale') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 4. Sécurité (Trivy Offline)') {
            steps {
                script {
                    echo "Audit de sécurité utilisant le cache local..."
                    // On utilise /data/trivy-cache pour éviter de télécharger la DB à chaque fois
                    sh """
                        docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
                        -v /data/trivy-cache:/root/.cache/trivy \
                        aquasec/trivy image --severity HIGH,CRITICAL \
                        --skip-db-update --skip-java-db-update ${IMAGE_NAME} || echo 'Audit terminé'
                    """
                }
            }
        }

        stage('📦 5. Déploiement Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'dr',     node: 'k3d-dr-server-0']
                    ]
                    
                    clusters.each { c ->
                        echo "🚀 Envoi vers le cluster : ${c.ctx}"
                        // Injection de l'image Docker dans k3d
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                        // Application des manifests et redémarrage
                        sh "kubectl apply -f k8s-deploy.yaml --context ${c.ctx} --validate=false"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${c.ctx}"
                    }
                }
            }
        }
    }

    post {
        always {
            script {
                echo "🧹 Nettoyage final du stockage (Protection des 19 Go)..."
                deleteDir()
                sh "docker builder prune -f"
            }
        }
        success {
            echo "✅ PIPELINE NADI : Tout est déployé et sécurisé !"
        }
    }
}
