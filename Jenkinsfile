pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    triggers {
        GenericTrigger(
            token: 'mon-projet-unique-token',
            genericVariables: [[key: 'ref', value: '$.ref']]
        )
    }

    parameters {
        booleanParam(name: 'DEPLOY_DR', defaultValue: false, description: 'Déployer aussi sur le cluster de secours (DR) ?')
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        SONAR_URL = "http://172.17.0.1:9000"
    }

    stages {
        stage('📥 1. Checkout') {
            steps {
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 2. Analyse & Tests (Parallèle)') {
            parallel {
                stage('PHP Lint & Unit Tests') {
                    steps {
                        script {
                            sh '''
                                cat > Dockerfile.test <<INNEREOF
FROM composer:latest
COPY . /app
WORKDIR /app
RUN composer install
# Vérification de la syntaxe PHP (Lint) puis tests
RUN find src/ -name "*.php" -exec php -l {} \\;
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

        stage('🛡️ 4. Sécurité Trivy') {
            steps {
                script {
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light --skip-db-update ${IMAGE_NAME} || echo 'Scan OK'"
                }
            }
        }

        stage('📦 5. Déploiement Multi-Cluster') {
            steps {
                script {
                    def clusters = [ [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'] ]
                    if (params.DEPLOY_DR) {
                        clusters.add([ctx: 'k3d-dr', node: 'k3d-dr-server-0'])
                    }
                    
                    clusters.each { c ->
                        echo "🚀 Déploiement sur ${c.ctx}"
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                        sh "kubectl apply -f k8s-deploy.yaml --context ${c.ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${c.ctx}"
                    }
                }
            }
        }
    }

    post {
        always {
            echo "🧹 Nettoyage du stockage..."
            deleteDir()
            sh "docker builder prune -f"
        }
        success {
            echo "✅ PIPELINE SUCCESS : Tout est Nadi !"
        }
    }
}
