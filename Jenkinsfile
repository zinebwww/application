pipeline {
    agent any

    triggers {
        GenericTrigger(
            genericVariables: [[key: 'ref', value: '$.ref']],
            token: 'mon-projet-unique-token', 
            causeString: 'Déclenchement automatique DevSecOps',
            printPostContent: true,
            silentResponse: false
        )
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
    }

    stages {
        stage('📥 1. Checkout') {
            steps {
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 2. Tests PHPUnit') {
            steps {
                script {
                    echo "Validation du code via PHPUnit (Docker)..."
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

        stage('🔍 3. Qualité SonarQube') {
            steps {
                // Utilise l'URL 172.17.0.1 configurée dans Jenkins
                withSonarQubeEnv('sonar-server') {
                    sh "${SCANNER_HOME}/bin/sonar-scanner"
                }
            }
        }

        stage('🐳 4. Build Image Docker') {
            steps {
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan Sécurité Trivy') {
            steps {
                script {
                    // --light pour économiser la RAM sur ta Kali
                    sh "docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --light ${IMAGE_NAME} || echo 'Scan fini'"
                }
            }
        }

        stage('📦 6. Déploiement Multi-Cluster') {
            steps {
                script {
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',     node: 'k3d-dr-server-0']
                    ]
                    
                    clusters.each { c ->
                        echo "Mise à jour du cluster : ${c.ctx}"
                        // Injection de l'image Docker
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                        // Restart du déploiement
                        sh "kubectl rollout restart deployment absence-app-deploy --context ${c.ctx} || echo 'Premier deploiement'"
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ PIPELINE SUCCESS : Tout est Nadi !"
        }
    }
}
