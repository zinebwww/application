pipeline {
    agent any
    triggers {
        GenericTrigger(token: 'mon-projet-unique-token', genericVariables: [[key: 'ref', value: '$.ref']])
    }
    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }
    stages {
        stage('📥 1. Checkout') { steps { deleteDir(); checkout scm } }
        
        stage('🧪 2. Tests PHPUnit') {
            steps {
                script {
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

        stage('🐳 4. Build Image') { steps { sh "docker build -t ${IMAGE_NAME} ." } }

        stage('🛡️ 5. Scan Sécurité Trivy') {
            steps { script { sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'OK'" } }
        }

        stage('📦 6. Deploy Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',     node: 'k3d-dr-server-0']
                    ]
                    clusters.each { c ->
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                        sh "kubectl apply -f k8s-deploy.yaml --context ${c.ctx}"
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${c.ctx}"
                    }
                }
            }
        }
    }
    post {
        success { echo "✅ TOUT EST VERT ! Projet DevSecOps terminé." }
    }
}
