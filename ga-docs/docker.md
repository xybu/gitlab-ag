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
To enable swap accounting feature on your Linux kernel, you may need to edit 
`/etc/default/grub` file and set 

```
GRUB_CMDLINE_LINUX="cgroup_enable=memory swapaccount=1"
```

Setup
=====

(1) Grant docker access permission to non-root users to avoid sudo

```bash
# add current user and www-data to docker group
sudo gpasswd -a ${USER} docker
sudo gpasswd -a www-data docker

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
apt-get update && apt-get upgrade
apt-get install -y build-essential cmake automake checkinstall gcc gdb software-properties-common binutils bison m4 cproto python3.4 python2.7 libcurl3 python3-pip python3-pexpect expect-dev empty-expect
apt-get autoclean

# create a user called "slave" to avoid default root permission
sudo useradd -m slave
# (END)

# Do the following in a bash process OUTSIDE the docker
sudo docker commit $CONTAINER_ID docker_username/image_name:tag
sudo docker push docker_username/image_name
# Use your own credential in the above two commands.
# For C development, I have created docker image "xybu/cdev:v1"

```

Integrate with gitlab-ag
========================

By default, `ga-hook/delegates/ga-grader_queue.py` enables Docker integration and assumes
the image `xybu/cdev:v1` which is configured as step 2 above specifies. If you want to 
disable Docker (not recommended) or use another image or change virtualization solution 
you will need to modify `ga-grader_queue.py` on your own.
