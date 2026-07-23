# Terraform IaC Specification for Enterprise Headless eCommerce Backend
terraform {
  required_version = ">= 1.5.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

variable "aws_region" {
  default = "us-east-1"
}

# 1. VPC & Networking
resource "aws_vpc" "main" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "ecommerce-production-vpc"
  }
}

# 2. AWS S3 Bucket for Media & Assets
resource "aws_s3_bucket" "product_media" {
  bucket = "ecommerce-headless-media-assets"
}

# 3. AWS ElastiCache Redis Cluster
resource "aws_elasticache_cluster" "redis" {
  cluster_id           = "ecommerce-redis-cluster"
  engine               = "redis"
  node_type            = "cache.t4g.micro"
  num_cache_nodes      = 1
  parameter_group_name = "default.redis7"
  port                 = 6379
}

# 4. AWS RDS Aurora MySQL Database Cluster
resource "aws_rds_cluster" "aurora_mysql" {
  cluster_identifier      = "ecommerce-aurora-cluster"
  engine                  = "aurora-mysql"
  engine_version          = "8.0.mysql_aurora.3.04.0"
  database_name           = "ecommerce"
  master_username         = "admin"
  master_password         = "SecureProdPassword123!"
  skip_final_snapshot     = true
}

# 5. AWS ECS Fargate Cluster & Service
resource "aws_ecs_cluster" "app_cluster" {
  name = "ecommerce-ecs-cluster"
}

resource "aws_ecs_task_definition" "app_task" {
  family                   = "ecommerce-api-task"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = 512
  memory                   = 1024

  container_definitions = jsonencode([
    {
      name      = "ecommerce-api"
      image     = "123456789012.dkr.ecr.us-east-1.amazonaws.com/ecommerce-api:latest"
      essential = true
      portMappings = [
        {
          containerPort = 9000
          hostPort      = 9000
        }
      ]
    }
  ])
}
