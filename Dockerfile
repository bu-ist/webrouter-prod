# These are all defined in the base image
#
FROM buist/websites-webrouter-base:2021.11.09

# for now this is our split and everything below this is for a different location
#
# the final default landscape should be test
ARG landscape=syst

# These files remains in the landscape specific CodePipeline area.
ADD landscape/${landscape}/vars.sh /etc/nginx/vars.sh
ADD landscape/${landscape}/hosts.map.erb /etc/erb/nginx/hosts.map.erb
ADD landscape/${landscape}/maps /etc/nginx/maps

