import os, re, json, sys
root = r'C:\\xampp\\htdocs\\clinic-system'
php_files = []
for dirpath, _, filenames in os.walk(root):
    for f in filenames:
        if f.lower().endswith('.php'):
            php_files.append(os.path.join(dirpath, f))
# Extract function definitions
func_defs = {}
func_pattern = re.compile(r'function\s+([a-zA-Z0-9_]+)\s*\(', re.IGNORECASE)
for file in php_files:
    try:
        with open(file, 'r', encoding='utf-8', errors='ignore') as fh:
            content = fh.read()
        for m in func_pattern.finditer(content):
            name = m.group(1)
            func_defs.setdefault(name, []).append(file)
    except Exception as e:
        pass
# Search for usages
used = set()
for name in func_defs.keys():
    # simple grep across all files
    pattern = re.compile(r'\b' + re.escape(name) + r'\b')
    for file in php_files:
        try:
            with open(file, 'r', encoding='utf-8', errors='ignore') as fh:
                txt = fh.read()
            if pattern.search(txt):
                used.add(name)
                break
        except Exception:
            continue
unused = {name: func_defs[name] for name in func_defs if name not in used}
# Find orphan files (not included/referenced)
include_pattern = re.compile(r'(include|require)(_once)?\s*\(?\s*[\"\']([^\"\']+)[\"\']\s*\)?', re.IGNORECASE)
referenced = set()
for file in php_files:
    try:
        with open(file, 'r', encoding='utf-8', errors='ignore') as fh:
            txt = fh.read()
        for m in include_pattern.finditer(txt):
            inc_path = m.group(3)
            # resolve relative to file directory
            inc_full = os.path.normpath(os.path.join(os.path.dirname(file), inc_path))
            if os.path.isfile(inc_full):
                referenced.add(inc_full)
    except Exception:
        pass
# Orphan files: php files in public/ that are not referenced and not entry points (i.e., not directly accessed via HTTP). We'll treat any file under public/ as potential entry point, so we only mark as orphan if not referenced and not in public/.
orphan = []
for file in php_files:
    if file not in referenced and not file.lower().endswith('index.php') and not file.lower().endswith('login.php'):
        # if file is under public/ but not referenced, still could be entry point, but we keep it as orphan for now.
        orphan.append(file)
result = {
    'unused_functions': unused,
    'orphan_files': orphan[:100]  # limit for safety
}
print(json.dumps(result, indent=2))
