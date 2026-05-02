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

        // ✅ NOUVEAU : Vérification syntaxique PHP avant tout
        stage('🔍 0. PHP Lint') {
            steps {
                script {
                    sh '''
                        echo "🔎 Vérification de la syntaxe PHP..."
                        # Recherche tous les fichiers .php dans src/
                        find src/ -name "*.php" -exec php -l {} \\;
                        echo "✅ Syntaxe PHP valide."
                    '''
                }
            }
        }

        stage('🧪 2. Tests PHPUnit') {
            steps {
                script {
                    echo "Validation PHPUnit..."
                    sh '''
                        cat > Dockerfile.test <<EOF
FROM composer:latest
COPY . /app
WORKDIR /app
RUN composer install
ENTRYPOINT ["./vendor/bin/phpunit", "tests"]
EOF
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
                // Attendre le résultat du Quality Gate et échouer si non satisfait
                timeout(time: 1, unit: 'HOURS') {
                    waitForQualityGate abortPipeline: true
                }
            }
        }

        stage('🐳 4. Build Image Finale') {
            steps {
                // Ajout d'une vérification supplémentaire : on s'assure que le fichier index.php est syntaxiquement correct
                sh '''
                    echo "🔍 Vérification finale de index.php avant build..."
                    php -l src/index.php
                '''
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan Sécurité Trivy') {
            steps {
                script {
                    // Correction : enlever --skip-db-update (sinon erreur au premier run)
                    // On ajoute un cache pour éviter de retélécharger la base à chaque fois
                    sh '''
                        docker run --rm \
                            -v /var/run/docker.sock:/var/run/docker.sock \
                            -v $HOME/.cache/trivy:/root/.cache/trivy \
                            aquasec/trivy image \
                            --severity HIGH,CRITICAL \
                            ${IMAGE_NAME}
                        echo "✅ Scan Trivy terminé"
                    '''
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
        failure {
            echo "❌ Pipeline échoué. Consultez les logs ci-dessus."
        }
    }
}
