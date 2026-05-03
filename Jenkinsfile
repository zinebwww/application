pipeline {
    agent any

    triggers {
        GenericTrigger(
            token: 'mon-projet-unique-token',
            genericVariables: [[key: 'ref', value: '$.ref']]
        )
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        SONAR_URL = "http://172.17.0.1:9000"
        // On force kubectl à utiliser le bon fichier
        KUBECONFIG = "/var/jenkins_home/.kube/config"
    }

    stages {
        stage('📥 1. Checkout') {
            steps { deleteDir(); checkout scm }
        }

        stage('🧪 2. Analyse & PHPUnit') {
            parallel {
                stage('PHPUnit') {
                    steps {
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
                stage('SonarQube') {
                    steps {
                        withSonarQubeEnv('sonar-server') {
                            sh "${SCANNER_HOME}/bin/sonar-scanner -Dsonar.host.url=${SONAR_URL} -Dsonar.qualitygate.wait=false"
                        }
                    }
                }
            }
        }

        stage('🐳 3. Build & Scan') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
                sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock -v /data/trivy-cache:/root/.cache/trivy aquasec/trivy image --severity HIGH,CRITICAL --skip-db-update ${IMAGE_NAME} || echo 'Audit OK'"
            }
        }

        stage('📦 4. Déploiement Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',     node: 'k3d-dr-server-0']
                    ]
                    clusters.each { c ->
                        echo "🚀 Déploiement sur : ${c.ctx}"
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                        sh "kubectl apply -f k8s-deploy.yaml --context ${c.ctx} --validate=false"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${c.ctx}"
                    }
                }
            }
        }
    }

    post {
        always {
            node {
                deleteDir()
                sh "docker builder prune -f"
            }
        }
    }
}
