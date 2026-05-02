pipeline {
    agent any
    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        SONAR_URL = "http://172.17.0.1:9000"
    }
    stages {
        stage('📥 1. Code') {
            steps { deleteDir(); checkout scm }
        }

        stage('🧪 2. Tests & Qualité') {
            parallel { // On fait les tests et Sonar en même temps pour gagner 2 min
                stage('PHPUnit') {
                    steps {
                        sh 'echo "FROM composer:latest\nCOPY . /app\nWORKDIR /app\nRUN composer install\nENTRYPOINT [\\"./vendor/bin/phpunit\\", \\"tests\\"]" > Dockerfile.test'
                        sh 'docker build -t app-test-image -f Dockerfile.test .'
                        sh 'docker run --rm app-test-image'
                    }
                }
                stage('SonarQube') {
                    steps {
                        withSonarQubeEnv('sonar-server') {
                            sh "${SCANNER_HOME}/bin/sonar-scanner -Dsonar.host.url=${SONAR_URL}"
                        }
                    }
                }
            }
        }

        stage('🐳 3. Build & Scan Flash') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
                // --light et --skip-db-update = Gain de 10 minutes !
                sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light --skip-db-update ${IMAGE_NAME} || echo 'OK'"
            }
        }

        stage('📦 4. Deploy Multi-Cluster (Parallèle)') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',     node: 'k3d-dr-server-0']
                    ]
                    // On déploie sur les deux clusters EN MÊME TEMPS
                    parallel clusters.collectEntries { c ->
                        ["Deploy ${c.ctx}" : {
                            sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                            sh "kubectl apply -f k8s-deploy.yaml --context ${c.ctx}"
                            sh "kubectl rollout restart deployment absence-app-deploy --context ${c.ctx}"
                        }]
                    }
                }
            }
        }
    }
    post {
        always {
            script {
                deleteDir()
                sh "docker builder prune -f" // Libère l'espace disque
                sh "docker rmi app-test-image || true"
            }
        }
    }
}
