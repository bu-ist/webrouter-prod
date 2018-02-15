#!/usr/bin/python
import sys
import csv

if len(sys.argv) < 2:
  print ""
  print "%s filename.csv" % sys.argv[0]
  sys.exit()

filename = sys.argv[1]

backends = dict()
with open(filename, 'r') as csvfile:
  items = csv.reader(csvfile)
  for row in items:
    #print '::'.join(row)
    #i = 0
    #for elem in row:
    #  print "[%d]=%s" % (i, elem)
    #  i += 1
    (path, level, backend, ts, size, comment) = row

    # file elements should be sent to the content server
    if backend == 'file':
      backend = 'content'

    # wordpress is the default for top-level so don't output anything in that case
    if backend == 'wordpress' and level == 'top-level':
      pass
    elif backend == 'type':
      pass
    elif backend == 'redirect2lower':
      print "# skipping %s as we should only add the redirect2lower if necessary" % (path)
    elif backend == 'app':
      print "##TODO: get phpmap entry for %s and put in separate file" % path
    else:
      print "  _/%s %s ;  # %s" % (path, backend, level)

    if backends.has_key(backend):
      backends[backend] += 1
    else:
      backends[backend] = 1

# now we print the backend summary
for backend in backends:
  print "### %d %s " % (backends[backend], backend)

