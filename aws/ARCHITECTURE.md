# AWS Cloud Deployment Architecture (Enterprise Magento-Style eCommerce)

This document describes the production cloud architecture for deploying this **Headless eCommerce REST API**. Designed for high traffic, low-latency API response times, and multi-region scalability.

---

## Cloud Architecture Overview

```
                      +----------------------------------+
                      |    Cloudflare / Route 53 CDN     |
                      +----------------+-----------------+
                                       |
                                       v
                      +----------------------------------+
                      |   AWS Application Load Balancer  |
                      +----------------+-----------------+
                                       |
                                       v
                      +----------------------------------+
                      | AWS ECS Fargate Tasks (Autoscale)|
                      +-------+------------------+-------+
                              |                  |
            +-----------------+                  +-----------------+
            | SQL Queries                        | Redis Cache & Locks
            v                                    v
  +-------------------+                +-------------------+
  | AWS Aurora MySQL  |                | AWS ElastiCache   |
  | (Primary + Read)  |                | Redis Cluster     |
  +-------------------+                +-------------------+
```

---

## Infrastructure Components

1. **Traffic Routing & Load Balancing**:
   - **AWS Route 53** DNS + SSL Termination on **AWS Application Load Balancer (ALB)**.
   - SSL certificates managed via AWS Certificate Manager (ACM).

2. **Compute Layer (AWS ECS Fargate)**:
   - Serverless container execution using AWS ECS Fargate.
   - Autoscaling policy: Scales out containers automatically when CPU > 70% or Request Count per Target exceeds threshold.

3. **Caching & Concurrency Layer (AWS ElastiCache Redis)**:
   - Sub-millisecond latency for Catalog queries.
   - Distributed Locks for Stock Reservation (`inventory:lock:variant:{id}`).
   - Queue driver for background async jobs (`ProcessOrderJob`).

4. **Relational Storage (AWS Aurora MySQL Multi-AZ)**:
   - Automated failover across multiple availability zones.
   - Read Replicas for offloading heavy catalog queries.

5. **Static Media Storage (AWS S3 + CloudFront)**:
   - S3 bucket for product images, brand assets, and customer invoice PDFs.
   - CloudFront CDN for global edge caching of static assets.

---

## CI/CD Pipeline (GitHub Actions -> AWS ECR -> ECS)

1. Developer pushes code to `main` branch on GitHub.
2. GitHub Actions runs PHPUnit & Pest tests.
3. Builds production Docker container image.
4. Pushes tagged container image to **AWS ECR (Elastic Container Registry)**.
5. Issues blue/green deployment trigger to **AWS ECS Fargate**.
