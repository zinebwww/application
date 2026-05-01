# Configuration des accès aux 3 clusters
provider "kubernetes" {
  alias          = "prod1"
  config_path    = "~/.kube/config"
  config_context = "prod-1"
}

provider "kubernetes" {
  alias          = "prod2"
  config_path    = "~/.kube/config"
  config_context = "prod-2"
}

provider "kubernetes" {
  alias          = "dr"
  config_path    = "~/.kube/config"
  config_context = "dr"
}

# Création automatique du Namespace Monitoring sur Prod-1
resource "kubernetes_namespace" "monitoring_prod1" {
  provider = kubernetes.prod1
  metadata { name = "monitoring" }
}

# Déploiement de l'application sur Prod-1 via Terraform
resource "kubernetes_manifest" "app_prod1" {
  provider = kubernetes.prod1
  manifest = yamldecode(file("../k8s-deploy.yaml"))
}

# Déploiement de l'application sur Prod-2
resource "kubernetes_manifest" "app_prod2" {
  provider = kubernetes.prod2
  manifest = yamldecode(file("../k8s-deploy.yaml"))
}

# Déploiement de l'application sur le cluster DR
resource "kubernetes_manifest" "app_dr" {
  provider = kubernetes.dr
  manifest = yamldecode(file("../k8s-deploy.yaml"))
}
