provider "aws" {
  region = var.aws_region
}

resource "aws_elastic_beanstalk_application" "php_app" {
  name        = "markdown2video-app"
  description = "PHP application deployed with Elastic Beanstalk"
}

resource "aws_elastic_beanstalk_environment" "php_app_env" {
  name                = "markdown2video-env"
  application         = aws_elastic_beanstalk_application.php_app.name
  solution_stack_name = "64bit Amazon Linux 2023 v4.6.2 running PHP 8.3"

  # Habilitar entorno de instancia única (sin balanceador)
  setting {
    namespace = "aws:elasticbeanstalk:environment"
    name      = "EnvironmentType"
    value     = "SingleInstance"
  }

  # Especificar el tipo de instancia EC2
  setting {
    namespace = "aws:autoscaling:launchconfiguration"
    name      = "InstanceType"
    value     = "t3.large"
  }

  setting {
      namespace = "aws:autoscaling:launchconfiguration"
      name      = "IamInstanceProfile"
      value     = "aws-elasticbeanstalk-ec2-role"
  }

  setting {
  namespace = "aws:elasticbeanstalk:application:environment"
  name      = "S3_BUCKET"
  value     = aws_s3_bucket.app_bucket.bucket
  }
}

resource "aws_db_instance" "mysql_db" {
  identifier           = "markdown2video-db"
  allocated_storage    = 20
  engine               = "mysql"
  engine_version       = "8.0"
  instance_class       = "db.t3.micro"
  db_name              = var.db_name
  username             = var.db_username
  password             = var.db_password
  publicly_accessible  = true
  skip_final_snapshot  = true
}


resource "aws_s3_bucket" "app_bucket" {
  bucket = "markdown2video-files"  # Cambia por un nombre único a nivel global

  tags = {
    Name        = "Markdown2VideoAppBucket"
    Environment = "production"
  }
}
