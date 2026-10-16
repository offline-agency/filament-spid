"""Bind ordered adapter observations to the unchanged upstream scenario IDs."""
import json
import re
import sys

report, log, output = sys.argv[1:]
data = json.load(open(report))
entries = [entry for group in data['test']['sp']['response'].values() for entry in group]
observations = [json.loads(line.split('LAB_RESPONSE ', 1)[1]) for line in open(log) if line.startswith('LAB_RESPONSE ')]
if len(entries) != len(observations):
    raise SystemExit('missing scenario diagnostics')
for entry, observation in zip(entries, observations):
    observation['scenario'] = re.match(r'Test \[([^]]+)\]', entry['test'])[1]
with open(output, 'w') as handle:
    json.dump(observations, handle, indent=2)
    handle.write('\n')
