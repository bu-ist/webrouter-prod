# Overview

The BU web router is an Nginx based AWS hosted routing system for our front-end web traffic. Our main www.bu.edu domain now utilizes this system for routing sites, apps, and redirects. For non-production see the [non-prod router](https://github.com/bu-ist/webrouter-nonprod).

For virtual host routing please refer to our [legacy routing documentation](https://developer.bu.edu/webteam/developer/resources/routing/). For additional detailed information about the webrouter please see our [SOP webrouter configuration docs](https://bushare.sharepoint.com/:w:/r/sites/ist/pm/support/other/web-fe-cloud/PLCdocs/IST-SOP-WebRouterConfiguration.docx?d=wada8f23d21674171977574c206fbc776&csf=1).

To verify if a route is being managed by the BU Webrouter, you can append `/server/lookup/` in front of any path. Routes that are managed by the Webrouter will respond with a snippet of information including which backend is servicing the request.

For instance, to see how requests to https://www.bu.edu/calendar/ are routed, visit https://www.bu.edu/server/lookup/calendar/

## Sites map

All of our non-redirect routes are in the [sites.map file](https://github.com/bu-ist/webrouter-prod/blob/prod/landscape/prod/maps/sites.map). All routing changes, including redirects, are implemented via this sites map file. The sites.map file can be considered a key-value mapping where the key is the path and the value is which backend the path resolves to.

For instance, when a request is made for https://www.bu.edu/calendar, the Nginx server looks up "calendar" in the sites map, sees that its' backend is listed as `phpbin` and then sends requests for /calendar to our php backends.

Please note that all paths in the sites.map file are prefaced with an underscore and slash to allow for easy searchability. For instance, the calendar path noted above is listed in sites.map as:

`_/calendar phpbin ;`

Also note the semicolon closing the line!

### Unknown/unlisted sites

As the majority of our routes/sites currently are served via [BU WordPress](https://www.bu.edu/tech/services/cccs/websites/www/wordpress/) the default destination for any site/route that is not explicitly listed in sites.map is the BU WordPress backends. Note, that there are very few routes listed with an explicit wordpress backend as any unlisted routes will automatically go to the WP backends.

That does mean that legitimate unknown routes will also be served a 404 response from BU WordPress's root site.

You can have sub-paths under the main path pointing at different backends. For instance, https://www.bu.edu/com/is a BU WordPress site and there is no root `_/com` entry in sites.map. But there are several entries for paths underneath the main com route.

For example, https://www.bu.edu/com/crc is a static micro-site hosted in our static content backend and thus has the below route so that `com/crc` requests don't go to the WP backends but instead the content backends:

`_/com/crc content ;`

#### Creating new non-Staging WordPress Sites

Having WordPress be our default back-end greatly reduces the complexity around adding new WordPress sites. The procedure for creating a new WordPress site in an AWS-routed environment is to simply use the Add New Site panel in wp-admin.

#### Creating new Staging WordPress Sites

www-staging is currently not going through the webrouter and is still routing via the legacy system. The impact of that is that creating a new Staging WordPress site requires, in brief:

    1. Creating the site in the Staging WordPress network.
    1. Create the AFS directory in the split-path
    1. Add a whole site in staging stub file
    1. Confirm site is available at www-staging.bu.edu/SITENAME

There are plans to move www-staging to the webrouter but in the interim the steps above are the summary of what needs to occur and for more detailed information see [Creating a new site in WordPress](https://developer.bu.edu/webteam/support/wordpress/site-management/creating-a-wordpress-site/#create-new-wp-site) and ignore the proxy_route steps.

#### Creating new non-WordPress Sites

The procedure for creating a new non-wordpress site has a few steps. First, add the site to the sites.map file for the DEVL and TEST landscapes [ for PROD for now please ask David King until a procedure is in place ]. You can see the available backends in the [cachecontrol file](https://github.com/bu-ist/webrouter-prod/blob/prod/landscape/prod/maps/cachecontrol.map).

Once committed and pushed, an AWS CodePipeline will build a new container image and deploy it automatically. If you have not received credentials to the non-prod AWS account and believe you should please ask Ron Yeany to ask Tim Carter to set you up.

After adding the file to the sites.map file for the appropriate environments, add the destination to the designated backend. For instance, if creating a new static site called new-static-site you would add:

```
_/new-static-site content ;
```

to the sites.map for the landscape(s) you want the site in. Then, create the new AFS volume,

```
/afs/.bu.edu/cwis/web/n/e/new-static-site
```

Finally, you will need to create a NAS/Isilon volume as well until we migrate from AFS. Log in to it.bu.edu and run the proxy_route tool to create the NAS volume. For instance to create the TEST volume:

```
/afs/bu/cwis/admin/proxy_route -e test new-static-site
```

After proxy_route completes and the new image deploys your new site should now be available at www-test.bu.edu/new-static-site [ and any other environments you’ve created the site in ].

## Redirects

[Example of redirect commit](https://github.com/dsmk/web-router-prod/commit/6b0532a81e78c2779bacf1afadd2b8d47538ce99)

Creating a redirect (also called a marketing URL) requires two changes to the web router. First, we need an entry in the sites.map file for the URL we want to redirect pointing it at the redirect_asis backend. Next we add an entry with the path we’re redirecting and the target URL it should redirect.

For instance, to redirect bu.edu/marketingredirect to www.example.com

sites.map:

```
_/marketingredirect redirect_asis ; #Any relevant info like ticket #
```

redirects.map:

```
_/marketingredirect https://www.example.com ;
```

Commit the change and submit a PR to push to PROD.

## HTML Domain Verification Files

HTML Domain Verification Files

Occasionally we receive requests to serve HTML files out of the root www.bu.edu domain to verify we do own bu.edu. For instance we have a www.bu.edu/p48pu6em39z6neb6rre8i4wx35x6dv.html file for Facebook and multiple files for Google www.bu.edu/google9f2e7abecb89081e.html.

For new requests, create the route in the web router by adding an entry listing the html filename and pointing it to the content backends. Start with the TEST environment repo, here’s an [example commit](https://github.com/dsmk/web-router-nonprod/commit/470675ac66d32f5f7e87e67c6dffccaecd0a4ca7), to test the procedure first.

```
_/p48pu6em39z6neb6rre8i4wx35x6dv.html content ;
```

While the web router builds and deploys, add the file to Isilon.

If the split path doesn’t exist, create it:

```
mkdir /cwis-shares/test-c1f-rw/web/p/4
```

Then, place the html file in the websites directory in the Isilon routing path:

```
touch /cwis-shares/test-c1f-rw/websites/p48pu6em39z6neb6rre8i4wx35x6dv.html
cat "p48pu6em39z6neb6rre8i4wx35x6dv.html" > /cwis-shares/test-c1f-rw/websites/p48pu6em39z6neb6rre8i4wx35x6dv.html
```

Finally, create a symlink from the route path to the split path directory you created earlier:

```
ln -s /cwis-shares/routefs-test/_route/websites/p48pu6em39z6neb6rre8i4wx35x6dv.html /cwis-shares/test-c1f-rw/web/p/4
```

Check the web router’s status in CodePipeline or try accessing the file directly or by doing a [server lookup](http://www-test.bu.edu/server/lookup/p48pu6em39z6neb6rre8i4wx35x6dv.html) to see which backend it’s routing to.

#### Legacy HTML Verification files

In the legacy AFS environment, these files exist under the split path for instance in /afs/bu.edu/cwis/web/g/o/ google9f2e7abecb89081e.html, and other google*.html files exist.


## Deploying changes

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

- https://blog.xebia.com/docker-container-secrets-aws-ecs/
