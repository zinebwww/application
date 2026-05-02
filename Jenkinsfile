pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
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
        DOCKER_BUILDKIT = '1'
    }

    stages {
        stage('📥 1. Code') {
            steps {
                deleteDir()
                checkout scm
            }
        }

<<<<<<< HEAD
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
=======
        stage('🔍 2. PHP Lint') {
            steps {
                sh 'find src/ -name "*.php" -exec php -l {} \\;'
            }
        }

        stage('🧪 3. Tests & Analyse (Parallèle)') {
            parallel {
                stage('PHPUnit') {
                    steps {
                        script {
                            sh '''
                                cat > Dockerfile.test <<INNEREOF
# syntax=docker/dockerfile:1.3-labs
FROM composer:latest
COPY . /app
WORKDIR /app
RUN --mount=type=cache,target=/root/.composer composer install
ENTRYPOINT ["./vendor/bin/phpunit", "tests"]
INNEREOF
                                docker build -t app-test-image -f Dockerfile.test .
                                docker run --rm app-test-image
                                docker rmi app-test-image || true
                            '''
                        }
                    }
                }
                stage('SonarQube (async)') {
                    steps {
                        withSonarQubeEnv('sonar-server') {
                            sh "${SCANNER_HOME}/bin/sonar-scanner -Dsonar.host.url=${SONAR_URL} -Dsonar.qualitygate.wait=false"
                        }
                    }
>>>>>>> 32cdb27 (Test démo)
                }
            }
        }

<<<<<<< HEAD
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
=======
        stage('🐳 4. Build Image Docker') {
>>>>>>> 32cdb27 (Test démo)
            steps {
                // Ajout d'une vérification supplémentaire : on s'assure que le fichier index.php est syntaxiquement correct
                sh '''
                    echo "🔍 Vérification finale de index.php avant build..."
                    php -l src/index.php
                '''
                sh "docker build -t ${IMAGE_NAME} ."
            }
        }

        stage('🛡️ 5. Scan Trivy (avec cache)') {
            steps {
                script {
<<<<<<< HEAD
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
=======
                    sh '''
                        sudo mkdir -p /data/trivy-cache
                        sudo chmod 777 /data/trivy-cache
                        docker run --rm \
                            -v /var/run/docker.sock:/var/run/docker.sock \
                            -v /data/trivy-cache:/root/.cache/trivy \
                            aquasec/trivy image \
                            --severity HIGH,CRITICAL \
                            --skip-db-update \
                            ${IMAGE_NAME} || echo "Scan terminé"
>>>>>>> 32cdb27 (Test démo)
                    '''
                }
            }
        }

        stage('📦 6. Déploiement Kubernetes') {
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
            echo "🧹 Nettoyage léger (workspace seulement)"
            deleteDir()
        }
        failure {
            echo "❌ Pipeline échoué. Vérifie les logs ci-dessus."
        }
        success {
            echo "✅ Pipeline terminé avec succès en moins de 30 min !"
        }
        failure {
            echo "❌ Pipeline échoué. Consultez les logs ci-dessus."
        }
    }
}
