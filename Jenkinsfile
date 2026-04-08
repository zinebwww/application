pipeline {
    agent any

    tools {
        sonarQube 'sonar-scanner'   // Le nom que tu as donné dans Tools
    }

    stages {
        stage('Checkout') {
            steps {
                git branch: 'main', url: 'https://github.com/zinebwww/application.git'
            }
        }

        stage('SonarQube Analysis') {
            steps {
                withSonarQubeEnv('SonarCloud') {
                    sh 'sonar-scanner -Dsonar.projectKey=zinebwww -Dsonar.organization=zinebwww -Dsonar.sources=.'
                }
            }
        }
    }
}
