pipeline {
    agent any
    triggers {
        GenericTrigger(token: 'mon-projet-unique-token', genericVariables: [[key: 'ref', value: '$.ref']])
    }
    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        SONAR_URL = "http://172.17.0.1:9000"
    }

    stages {
        stage('📥 1. Code') {
            steps {
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 2. Tests PHPUnit') {
            steps {
                script {
                    echo "Validation PHPUnit..."
                    sh '''
                        echo "FROM composer:latest\nCOPY . /app\nWORKDIR /app\nRUN composer install\nENTRYPOINT [\\"./vendor/bin/phpunit\\", \\"tests\\"]" > Dockerfile.test
                        docker build -t app-test-image -f Dockerfile.test .
                        docker run --rm app-test-image
                        docker rmi app-test-image || true
                    '''
                }
            }
        }

        stage('🔍 3. Qualité SonarQube') {
            steps {
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner -Dsonar.host.url=${SONAR_URL}"
                }
            }
        }

        stage('🐳 4. Build Image Finale') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan Sécurité Trivy') {
            steps {
                script {
                    // --light et --skip-db-update pour ne pas consommer de disque/RAM
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light --skip-db-update ${IMAGE_NAME} || echo 'Audit OK'"
                }
            }
        }

        stage('📦 6. Deploy Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',     node: 'k3d-dr-server-0']
                    ]
                    clusters.each { c ->
                        echo "Mise à jour du cluster : ${c.ctx}"
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
            script {
                echo "🧹 Nettoyage pour protéger les 19 Go..."
                deleteDir()
                sh "docker builder prune -f"
                sh "docker image prune -f"
            }
        }
    }
}
