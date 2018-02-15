#!/usr/bin/python
# 
# Simple script to scan the www.bu.edu directories and summarize their status.  The goal of this is to
# categorize sites into one of the following groups:
# - WordPress
# - public non-WordPress static content (can easily migrate to S3)
# - non-WordPress static content which will need Apache server (.htaccess and/or .asis)
# - App sites (baseline environment then uses map file to determine where they should go)
# 
# First it determines the status of all top-level directories (WordPress, non-WordPress, or App).  
#
# For WordPress areas it checks:
# - determine if there are any ignore_*.cms files in subdirectories - if so record and scan the sub-area.
# - determine what landscapes WordPress is using.
#
# For non-WordPress areas it checks:
# - determine if there are any .htaccess files.
# - determine if there are any asis files.
# - determine if there are only asis files.
#
import glob
import os
import time
import sys
import httplib

class scan_buedu:

  def __init__ (self, landscape):
    self._subdirlevel = 3
    self.landscape = landscape
    if landscape == 'prod':
      self.wordpress_stub = 'whole_site_in.cms'
      self.app_stub = 'whole_site_is.app'
      self.ignore_stub = 'ignore_prod.cms'
      self.host = 'www.bu.edu'
    elif landscape == 'test':
      self.wordpress_stub = 'whole_test_site_in.cms'
      self.app_stub = 'whole_test_site_is.app'
      self.ignore_stub = 'ignore_test.cms'
      self.host = 'www-test.bu.edu'
    elif landscape == 'devl':
      self.wordpress_stub = 'whole_devl_site_in.cms'
      self.app_stub = 'whole_devl_site_is.app'
      self.ignore_stub = 'ignore_devl.cms'
      self.host = 'www-devl.bu.edu'
    elif landscape == 'syst':
      self.wordpress_stub = 'whole_syst_site_in.cms'
      self.app_stub = 'whole_syst_site_is.app'
      self.ignore_stub = 'ignore_syst.cms'
      self.host = 'www-syst.bu.edu'

    self.cached_top_level = {
     # 'ccboard': [ 'content-apache', '2/18/2010 11:20', '220461', '(cached 9/13/2016) dirs=31447 htaccess=yes asis=no' ],
     # 'dev': [ 'content-apache', '9/12/2016 3:00', '125022', '(cached 9/13/2016) dirs=28196 htaccess=yes asis=yes' ],
     # 'systems-programming': [ 'content-apache', '9/12/2016 19:17', '106822', '(cached 9/13/2016) dirs=1785 htaccess=yes asis=yes' ],
     # 'systems-programming.RESTORED': [ 'content-apache', '9/12/2016 19:20', '106452', '(cached 9/13/2016) dirs=1733 htaccess=yes asis=yes' ],
     # 'nisprod': [ 'content-apache', '9/12/2016 19:27', '84481', '(cached 9/13/2016) dirs=7263 htaccess=yes asis=yes' ],
    }


  def test_path (self, path):
    conn = httplib.HTTPConnection(self.host)
    conn.request('GET', path)
    resp = conn.getresponse()
    return resp.status

 
  def is_wordpress (self, path):
    #stub_search = '%s/whole*_site_in.cms' % path
    stub_search = '%s/%s' % ( path, self.wordpress_stub )
    #print "stub_search: %s" % stub_search
    stubs = glob.glob(stub_search)
    #print "stubs(%d): %s" % (len(stubs), stubs)
    return len(stubs) > 0

  def format_timestamp (self, tstamp):
    return time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(tstamp))

  def output_entry (self, name, e_top, e_type, e_time, e_num, comment):
    print '"%s","%s","%s","%s","%s","%s"' % (name, e_top, e_type, e_time, e_num, comment)

  def evaluate_directory (self, path, name=None, is_top="top-level"):
    if not name:
      name = os.path.basename(path)

    # special checks for top level sites only
    if is_top:
      # check for upper case characters in name
      num_uppercase = sum(1 for c in name if c.isupper() )
      if num_uppercase > 0:
        self.output_entry(name, is_top, "redirect2lower", "", "", "Apache modules redirects this to lowercase")
        return

      # check for top-level cached directories - these are used to minimize performance
      # impact of multiple rules
      if self.cached_top_level.has_key(name):
        result = self.cached_top_level[name]
        self.output_entry(name, is_top, result[0], result[1], result[2], result[3])
        return

    self._evaluate_entry(path, name, is_top)

  def _evaluate_entry (self, path, name, is_top):
    #print "_eval_entry(%s, %s, %s)" % (path, name, is_top)
    if os.path.isfile(path):
      self._evaluate_file(path, name, is_top)
    elif os.path.islink(path):
      self._evaluate_link(path, name, is_top)
    elif self.is_wordpress(path):
      self._evaluate_wordpress(path, name, is_top)
    elif os.path.isfile(os.path.join(path, self.app_stub)):
      self._evaluate_app(path, name, is_top)
    else:
      self._evaluate_non_wordpress(path, name, is_top)

  def _evaluate_link (self, path, name, is_top):
    try:
      real_path = os.path.realpath(path)
    except:
      real_path = "error getting real path"

    # now that we have the realpath we recurse on the eval
    self._evaluate_entry(real_path, name, is_top)

    #self.output_entry(name, is_top, "link", "", "", real_path)

  def _evaluate_file (self, path, name, is_top):
    try:
      tstamp = self.format_timestamp(os.path.getmtime(path))
    except:
      raise
      tstamp = "error"

    self.output_entry(name, is_top, "file", tstamp, "", "")

  def _evaluate_wordpress (self, path, name, is_top):
    #print "wordpress: %s" % path

    # figure out if any subdirectories have the ignore_prod.cms
    has_wp_assets = "no"
    #microsite_search = '%s/*/ignore_*.cms' % path
    microsite_search = '%s/*/%s' % ( path, self.ignore_stub)
    micro_sites = glob.iglob(microsite_search)
    usites_done = {}
    for usite_stub in micro_sites:
      usite = os.path.dirname(usite_stub)
      usite_base = os.path.basename(usite)
      usite_name = "%s/%s" % (name, usite_base)
      if not usites_done.has_key(usite_base):
        #print "***** %s: %s" % (usite, usite_stub)
        # we evaluate the micro site no matter what
        self.evaluate_directory(usite, usite_name, "")
        # we only set the microsite if the name is different from wp-assets
        if usite_base == 'wp-assets':
          has_wp_assets = 'yes'
        else:
          usites_done[usite_base] = 1

    usites = usites_done.keys()
    self.output_entry(name, is_top, "wordpress", "", len(usites), 
      "wp-assets=%s other=%s" % (has_wp_assets, ";".join(usites)) )

  def _evaluate_non_wordpress (self, path, name, is_top):
    try:
      tstamp = self.format_timestamp(os.path.getmtime(path))
    except:
      tstamp = "tstamp_error"
    self.output_entry(name, is_top, "content", tstamp, -1, "")

  def _evaluate_non_wordpress_indepth (self, path, name, is_top):
    #print "non_wordpress: %s" % path

    # check for .htaccess and asis files
    num_htaccess = 0
    num_asis = 0
    num_xml = 0
    stubs = []
    has_root_index = 'no'
    html_name = None
    sub_type = 'content-public' 
    newest_timestamp = 0
    total_files = 0
    total_dirs = 0
    num_sep_path = path.count(os.path.sep)
    for root, dirs, files in os.walk(path):
      # increase our file and dir tallies
      total_files = total_files + len(files)
      total_dirs = total_dirs + len(dirs)
      # go through all our files for two reasons:
      # 1) to determine which file was modified most recently
      # 2) to figure out the filtered list of files to determine if anything other than .asis
      #    files
      #
      reduced = []
      for fname in files:
        # check if more recently modified
        try:
          tstamp = os.path.getmtime(os.path.join(root, fname))
          if tstamp > newest_timestamp:
            newest_timestamp = tstamp
        except:
          pass

        # if .htaccess then set has field
        if fname == '.htaccess':
          num_htaccess += 1

        elif fname[-5:] == '.asis' :
          num_asis += 1

        elif fname[-4:] == '.xml' :
          num_xml += 1
          reduced.append(fname)

        elif fname[-4:] == '.cms' and root == path :
          stubs.append(fname[:-4])

        elif fname[:6] == 'index.' and root == path:
          # if we find an index file then record it
          has_root_index = 'yes'
          if not html_name :
            html_name = os.path.join(root, fname)
          reduced.append(fname)

        elif fname[-5:] == '.html' or fname[-4:] == '.htm' :
          # record at least one file if we find it
          if not html_name :
            html_name = os.path.join(root, fname)
          reduced.append(fname)
 
        else:
          reduced.append(fname)

      # tests for only the top level
      #if root == path :
        # first we should 
      # if there are no files other than *.asis then this is a simple redirect
      #reduced = [x for x in files if x[-5:] != '.asis' and x != '.htaccess' and x[-4:] != '.cms' ]
      if root == path and len(dirs) == 0 and len(reduced) == 0:
        if len(files) == 0:
          sub_type = 'content-empty'
        elif num_asis > 0 :
          sub_type = 'content-redirect'
        elif len(stubs) > 0:
          sub_type = 'content-other-wordpress'
     
      # remove all directories if below our max level
      if num_sep_path + self._subdirlevel <= root.count(os.path.sep):
        del dirs[:]

    if sub_type == 'content-public':
      # if we have not set the sub_type in any of the sub-areas then we can consider
      # types based on htaccess and asis
      #
      if num_htaccess > 0 or num_xml > 0 :
        sub_type = 'content-apache'
      elif num_asis > 0 :
        sub_type = 'content-s3redirect'

    # remove the prefix from the html_name to keep it short
    if html_name:
      start_pos = len(path)
      short_html = html_name[start_pos:]
      sample = '/%s%s' % (name, short_html)
      # now that we have generated the sample path let's do a web request to test it
      retcode = self.test_path(sample)
      #print "%s: start_pos=%d short_html=%s html_name=%s" % (name, start_pos, short_html, html_name) 

    else:
      sample = ""
      retcode = '-'

    self.output_entry(name, is_top, sub_type, self.format_timestamp(newest_timestamp), total_files, 
      "dirs=%d htaccess=%d asis=%d xml=%d has_index=%s sample=%s ret=%s stubs=%s" % 
      (total_dirs, num_htaccess, num_asis, num_xml, has_root_index, sample, retcode, ";".join(stubs) ) )
 
  def _evaluate_app (self, path, name, is_top):
    #print "app: %s" % path
    self.output_entry(name, is_top, "app", "", "", "look at map file")

prefix = '/afs/.bu.edu/cwis/web'
subset = '?/?'
arg_len = len(sys.argv)
if arg_len > 1:
  landscape = sys.argv[1]
  if arg_len > 2:
    prefix = sys.argv[2]
  else:
    prefix = "/cwis-shares/%s-rro/web" % landscape
  if arg_len > 3:
    subset = sys.argv[3]

  #print "%s" % repr(sys.argv)
  #sys.exit()
else:
  print ""
  print "%s landscape prefix [limit]" % sys.argv[0]
  print "  landscape = syst,devl,test,prod"
  print "  prefix = directory prefix (default /cwis-shares/<landscape>-rro/web rather than /afs/.bu.edu/cwis/web/)"
  print "  subset = first letter/second letter (default is ?/? for all directories)"
  print ""
  sys.exit()

#print "prefix=%s subset=%s landscape=%s" % (prefix, subset, landscape)
top_levels = glob.iglob('%s/%s/*' % (prefix, subset) )
#top_levels = glob.iglob('/afs/.bu.edu/cwis/web/A/V/*')
#top_levels = glob.iglob('/afs/.bu.edu/cwis/web/m/y/*')
#top_levels = glob.iglob('/afs/.bu.edu/cwis/web/t/e/*')
#top_levels = glob.iglob('/afs/.bu.edu/cwis/web/C/O/*')
#top_levels = glob.iglob('/afs/.bu.edu/cwis/web/i/n/*')

scan = scan_buedu(landscape)
print '"path","is_top_level","type","modtime","size","comment"'

for top in top_levels:
  scan.evaluate_directory(top)

# vi: ts=2 expandtab
