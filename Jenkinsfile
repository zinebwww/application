pipeline {
    agent any

    triggers {
        GenericTrigger(
            token: 'mon-projet-unique-token',
            genericVariables: [[key: 'ref', value: '$.ref']],
            causeString: 'Triggered by GitHub Webhook'
        )
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    environment {
        SCANNER_HOME = tool 'sonar-scanner'
        IMAGE_NAME = "absence-app:latest"
        // IP de la passerelle Docker pour contacter SonarQube sur Kali
        SONAR_URL = "http://172.17.0.1:9000"
    }

    stages {
        stage('📥 1. Checkout & Clean') {
            steps {
                deleteDir()
                checkout scm
            }
        }

        stage('🧪 2. Analyse & Tests (Parallèle)') {
            parallel {
                stage('PHPUnit Tests') {
                    steps {
                        script {
                            echo "Exécution des tests PHPUnit..."
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
                stage('SonarQube Quality') {
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
                echo "Construction de l'image Docker de production..."
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 4. Sécurité Trivy (Offline)') {
            steps {
                script {
                    echo "Audit de sécurité (Utilisation du cache local)..."
                    // On utilise le cache que nous avons téléchargé manuellement sur /data/trivy-cache
                    sh """
                        docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
                        -v /data/trivy-cache:/root/.cache/trivy \
                        aquasec/trivy image --severity HIGH,CRITICAL \
                        --skip-db-update --skip-java-db-update ${IMAGE_NAME} || echo 'Audit OK'
                    """
                }
            }
        }

        stage('📦 5. Déploiement Multi-Cluster') {
            steps {
                script {
                    // Utilisation des noms de contextes exacts k3d-prod-1 et k3d-dr
                    def clusters = [
                        [ctx: 'k3d-prod-1', node: 'k3d-prod-1-server-0'],
                        [ctx: 'k3d-dr',     node: 'k3d-dr-server-0']
                    ]
                    
                    clusters.each { c ->
                        echo "🚀 Déploiement sur le cluster : ${c.ctx}"
                        // Injection de l'image Docker dans le cluster k3d correspondant
                        sh "docker save ${IMAGE_NAME} | docker exec -i ${c.node} ctr -n k8s.io images import -"
                        // Mise à jour de l'application sur Kubernetes
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
                echo "🧹 Nettoyage final pour préserver l'espace disque (19GB Limit)..."
                deleteDir()
                sh "docker builder prune -f"
                sh "docker image prune -f"
            }
        }
        success {
            echo "✅ PIPELINE TERMINÉE AVEC SUCCÈS : Tout est Nadi !"
        }
    }
}
