# Overview

This repo contains the landscape specific files for the BU web router.  In general updates to this repo are
safe to release to in a change restriction period.  That is because:

- The version of the base image is date tagged to ensure that no software updates accidently sneak through.
- The config files in this repo are limited to map files
- The CodeBuild part of the release process will run nginx -t to ensure that there are no map errors prior
  to release.
- The CodePipeline will back out to the old running version if the updated version does not stabilize.

# Operational Tasks

## Changing a map entry

1. Use `git pull` to ensure that we are operating with the latest version of the configuration.
2. Use `git status` to double-check that you are on the correct landscape.  If not then do `git checkout landscape`
3. Edit landscape/LANDSCAPE/maps/sites.map to reflect our map path.
4. Use `git status` to double-check status of files.
5. Use `git diff` to see what changed.
6. Use `git add` to select files for inclusion.
7. Use `git commit -m "description" ` to commit the change with the description of the change
8. Use `git push` to push these changes out to the GitHub repo.  This will trigger the CodePipeline update.
9. Watch the CodePipeline release through the web console or cli for any issues.

## Updating to a newer base image

During initial builds and configuration we are using the tag latest.

1. Use `git pull` to ensure that we are operating with the latest version of the configuration.
2. Use `git status` to double-check status of files.
3. Edit Dockerfile and change the tag as part of the `FROM dsmk/web-router-base:date`
4. Use `git status` to double-check status of files.
5. Use `git diff` to see what changed.
6. Use `git add` to select files for inclusion.
7. Use `git commit -m "description" ` to commit the change with the description of the change
8. Use `git push` to push these changes out to the GitHub repo.  This will trigger the CodePipeline update.
9. Watch the CodePipeline release through the web console or cli for any issues.

## Adding a new landscape

1. Clone a new directory with the landscape name `git clone git@github.com:dsmk/web-router-nonprod.git test`
2. Cd into the directory and create and checkout the new branch: `cd test; git checkout -b test`
3. Create the landscape specific subdirectory: `mkdir -p landscape/test/maps`
4. Copy initial files from a previous landscape except for the sites.map which comes from landscape/common/sites-common.map
5. Edit files as appropriate.
6. For sites.map, use PHPMAP and the filesystem scan to find things.


This repo has all the data for the BU AWS web front-end proof of concept.  The architecture sets up a fairly 
standard AWS Elastic Container Service (ECS) environment with CloudFront in front of it.  The CloudFormation 
templates are in the aws/ subdirectory but eventually should be in a separate location.

This is still a work in progress and we still need to address the following issues:

- Patching model for ECS EC2 instances: ECS does not seem to handle this by default.  Is the correct approach
  to use EC2 Systems Manager?

- Method of updating route configuration: This version bakes the routing into the Docker image and relies upon
  building a new Docker image when we change the configuration.  The CodePipeline makes that more straightforward
  but a nice to have would be a simple interface to adjust the change as long as it does not add much complexity 
  to the solution.  

- Release mechanism for multiple landscapes: This POC code only handles one landscape (test).  I have seen a
  different approaches to multiple landscapes:  1) Have a single CodePipeline for multiple landscapes with a 
  manual approval step prior to production; 2) Have multiple CodePipelines like the POC based on different 
  repos and/or tags.  

- Approach for handling redirection of both entire subdirectories and singleton URLs: Redirection ideally 
  should be managed by Help Center folks.  I have seen a few references to lambda/API gateway and S3 bucket 
  based approaches.  If we use those solutions then hopefully the routing table could just have a "redirect" 
  backend that points to the solution.

- Internal load balancers for backends: Our existing front-ends handle load balancing for the WordPress and 
  Django backends (mod_proxy_balancer Apache 2.2).  One refinement would be to have internal application load
  balancers so all load balancing is handled (and monitored/tracked) by AWS load balancing services.  In 
  addition, the WordPress ALB could potentially handle selecting between Application and Asset servers which 
  would simplify this NGINX/HTTPD configuration even more. 

The general workflow while we are building the system is to:

1) Update the test/sut/test_app.sh script to test the new functionality with the current Solaris.  This involves 
   running the script like so `./test/sut/test_app.sh -S test www-test.bu.edu`  - the -S disables the check for 
   upstream headers (only configured in NGINX for now).

2) Once that is done then one needs to update the NGINX configuration to implement the same functionality as 
   Solaris.  This mainly will involve the \*.conf.erb files in the main directory but make involve new environment
   entries.  One can test this on a local build box by running `docker-compose -f www-test.bu.edu.yaml up --build` 
   and then `./test/sut/test_app.sh test`  This is testing with the normal www-test backends.

3) Next the automatic testing needs to be configured.  This involves two sets of changes - `autotest-test.yml` 
   docker-compose file and updates to the `test/backend` Docker image.  The docker-compose file will create a
   container to run the tests, an NGINX frontend container, and a test backend container which emulates test
   responses.  The goal of this test framework is to test and minimize regressions in the frontend containers.  It
   is not intended to be a test of whole services.

4) One can pretest the automatic testing by doing `docker-compose -f autotest-test.yaml up --build ` - if 
   everything is OK then the last line will end "exited with code 0."  Control-C to exit from the test and 
   it will stop all the containers set up.  The test output is stored in the `test/results/` directory and you 
   can see the specific test that failed by searching for `exit code: 1` 

5) When you are ready to release changes you need to check the changes into Git and push them to GitHub.  Here 
   are some common commands:
        a) `git status` - see which files have been modified, which are new, what is selected for commiting, and 
           the branch.
        b) `git diff [file]` - see what has changed from the last commit.
        c) `git add file` - add a file to the list of things to check in.
        d) `git commit -m 'msg'` - commit added files with the message msg (include INC* and ENC* where appropriate)
        e) `git push` - push the changes to github

6) Right now one needs to go to hub.docker.com/r/dsmk/bufe-buedu, select "Build Settings", and select the Trigger
   to start the build.  This will be replaced later.  You can see the status of the build by going to "Build Details"

7) Once the new image is done you can replace the existing container by going to the ECS web console, selecting
   the task we want to modify (bufe-bu.edu-bufe:num), select "Create new revision", and then selecting "Create".  
   This will take a while to run because ECS will create a new container, add it to the ELB/ALB, and wait for the 
   old one to drain completely before being done.  This will be replaced later.

URLs for this process:
- https://sanderknape.com/2016/06/getting-ssl-labs-rating-nginx/
- https://sanderknape.com/2017/08/custom-cloudformation-resource-example-codedeploy/?__s=1qw3d4dssvntsxck5tuk
- https://aws.amazon.com/blogs/compute/managing-secrets-for-amazon-ecs-applications-using-parameter-store-and-iam-roles-for-tasks/

- http://blog.xebia.com/docker-container-secrets-aws-ecs/

