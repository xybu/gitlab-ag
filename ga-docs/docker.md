Docker
======

"Docker is an open-source project that automates the deployment of applications 
inside software containers."

Installation
============

To install Docker on Ubuntu 14.04,

```bash
curl -sSL https://get.docker.com/ubuntu/ | sudo sh
```

Setup
=====

(1) Create a non-root user to avoid sudo

```bash
# create a user called "slave"
# keep password empty so it cannot be logged in via SSH
sudo useradd -m slave

# add current user and "slave" to docker group
sudo gpasswd -a slave docker
sudo gpasswd -a ${USER} docker

# restart docker service
sudo service docker restart

# refresh group settings immediately
newgrp docker

# Now you can run docker without sudo
```

(2) Create Image

This following commands show how to create a docker image from scratch. For simplicity
you can just pull image `xybu/c_dev:jan_15` and `docker run -t -i xybu/c_dev:jan_15 bash` will
give you a bash process inside the container.

```bash
# Pull latest ubuntu image
sudo docker pull ubuntu

# Create a container that starts with bash
CONTAINER_ID=`sudo docker create -t -i ubuntu bash`
# Write down the container id

# Run the docker and install necessary packages
sudo docker start -a -i $CONTAINER_ID

# The following commands run inside docker container
apt-get update
apt-get install -y build-essential cmake automake checkinstall gcc gdb software-properties-common
apt-get autoclean
# (END)

# Do the following in a bash process OUTSIDE the docker
sudo docker commit $CONTAINER_ID docker_username/image_name:tag
sudo docker push docker_username/image_name
# Use your own credential in the above two commands.
# For C development, I have created docker image "xybu/c_dev:jan_15"

```

Integrate with gitlab-ag
========================


