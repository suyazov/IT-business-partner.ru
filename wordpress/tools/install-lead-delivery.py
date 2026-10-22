#!/usr/bin/env python3
"""Install only the separately authorized lead sender; no bot changes or token copies.

Run from the independently reviewed source checkout after exact owner approval.
Creates bounded, documented resources and reloads systemd. Enabling the timer
and publishing the WordPress plugin are separate final deployment steps.
"""
import hashlib,json,os,pathlib,pwd,stat,subprocess,sys,tempfile
import yaml

SOURCE=pathlib.Path(__file__).resolve().parents[1]
BASE=pathlib.Path('/var/lib/sy3-wordpress/leads')
CONFIG=pathlib.Path('/root/.config/client-access/sy3.ru/lead-delivery.json')
TARGET=pathlib.Path('/usr/local/lib/sy3-leads/dispatcher.py')
ACCESS=pathlib.Path('/root/.config/client-access/sy3-support')

def guarded_directory(path,mode,uid=0,gid=0):
    if path.is_symlink():raise RuntimeError('unexpected_symlink')
    if path.exists():
        st=path.stat()
        if not stat.S_ISDIR(st.st_mode) or (st.st_uid,st.st_gid,stat.S_IMODE(st.st_mode))!=(uid,gid,mode):raise RuntimeError('unexpected_directory_permissions')
    else:path.mkdir(mode=mode);os.chown(path,uid,gid);os.chmod(path,mode)

def protected_read(path):
    st=path.lstat()
    if not stat.S_ISREG(st.st_mode) or st.st_nlink!=1 or st.st_uid!=0 or stat.S_IMODE(st.st_mode)&0o007:raise RuntimeError('unsafe_owner_source')
    return path.read_text()

def env_values(path):
    import shlex
    result={}
    for line in protected_read(path).splitlines():
        if not line.strip() or line.lstrip().startswith('#'):continue
        parts=shlex.split(line,comments=True)
        if len(parts)!=1 or '=' not in parts[0]:raise RuntimeError('invalid_environment_source')
        key,value=parts[0].split('=',1);result[key]=value
    return result

def install_file(path,raw,mode,gid=0):
    if path.is_symlink():raise RuntimeError('unexpected_symlink')
    if path.exists():
        st=path.stat()
        if not stat.S_ISREG(st.st_mode) or st.st_uid!=0 or st.st_gid!=gid or stat.S_IMODE(st.st_mode)!=mode or st.st_nlink!=1:raise RuntimeError('unexpected_existing_file')
        if path.read_bytes()!=raw:raise RuntimeError('existing_file_differs_preserve_and_reconcile')
        return
    fd,name=tempfile.mkstemp(prefix='.sy3-leads-install-',dir=path.parent)
    try:
        with os.fdopen(fd,'wb')as f:f.write(raw);f.flush();os.fsync(f.fileno())
        os.chmod(name,mode);os.chown(name,0,gid);os.replace(name,path)
    finally:
        if os.path.exists(name):os.unlink(name)

def main():
    if os.geteuid()!=0 or os.environ.get('SY3_LEADS_ALLOW_PRODUCTION')!='1':raise RuntimeError('exact_production_authority_required')
    if len(sys.argv)!=2 or sys.argv[1]!='--install':raise RuntimeError('expected_install_flag')
    import re
    owner_pid=os.environ.get('SY3_LEADS_LOCK_PID','')
    if not owner_pid.isdigit():raise RuntimeError('exact_project_lock_owner_required')
    expected=os.environ.get('SY3_LEADS_REVIEWED_HEAD','')
    actual=subprocess.check_output(['git','rev-parse','HEAD'],cwd=SOURCE.parent,text=True).strip()
    if not re.fullmatch('[a-f0-9]{40}',expected) or actual!=expected:raise RuntimeError('reviewed_source_head_required')
    if subprocess.check_output(['git','status','--porcelain'],cwd=SOURCE.parent,text=True).strip():raise RuntimeError('reviewed_source_must_be_clean')
    locks=subprocess.check_output(['lslocks','-n','-o','PID,PATH'],text=True)
    for path in ['/run/lock/kimi-projects/sy3.ru.lock','/run/lock/kimi-projects/suyazov-IT-business-partner.ru.lock']:
        if not re.search(r'^\s*'+re.escape(owner_pid)+r'\s+'+re.escape(path)+r'\s*$',locks,re.M):raise RuntimeError('project_lock_required')
    telegram=yaml.safe_load(protected_read(pathlib.Path('/etc/sy3-support/projects.yml')))
    maximum=yaml.safe_load(protected_read(pathlib.Path('/etc/sy3-support/max-projects.yml')))
    owners=telegram.get('owners')
    if not isinstance(owners,list) or len(owners)!=1 or type(owners[0])is not int or owners[0]<=0:raise RuntimeError('telegram_owner_unresolved')
    max_env=env_values(ACCESS/'max.env');max_owner=int(max_env.get('MAX_OWNER_ID','0'))
    if max_owner<=0 or maximum.get('owners')!=[max_owner]:raise RuntimeError('max_owner_unresolved')
    if not env_values(ACCESS/'telegram-bot.env').get('TELEGRAM_BOT_TOKEN') or not max_env.get('MAX_BOT_TOKEN'):raise RuntimeError('bot_access_missing')
    config={'version':1,'telegram_owner_id':owners[0],'max_owner_id':max_owner}
    group=pwd.getpwnam('php-sy3').pw_gid
    guarded_directory(BASE.parent,0o750,0,group)
    guarded_directory(BASE,0o750,0,group)
    for name in ['queue','locks','limits']:guarded_directory(BASE/name,0o770,0,group)
    guarded_directory(BASE/'state',0o750,0,group)
    guarded_directory(CONFIG.parent,0o700)
    install_file(CONFIG,(json.dumps(config,sort_keys=True)+'\n').encode(),0o600)
    guarded_directory(TARGET.parent,0o755)
    raw=(SOURCE/'delivery/dispatcher.py').read_bytes();install_file(TARGET,raw,0o644)
    for name in ['sy3-leads.service','sy3-leads.timer']:
        install_file(pathlib.Path('/etc/systemd/system')/name,(SOURCE/'deploy/systemd'/name).read_bytes(),0o644)
    subprocess.run(['systemctl','daemon-reload'],check=True)
    subprocess.run(['/usr/bin/python3',str(TARGET),'--check'],check=True)
    print(json.dumps({'installed':True,'dispatcher_sha256':hashlib.sha256(raw).hexdigest(),'owner_targets':{'telegram':1,'max':1},'token_copies_created':False,'timer_enabled_by_installer':False}))

if __name__=='__main__':
    try:main()
    except Exception:
        print('{"error":"lead_installation_stopped_preserve_existing_state"}',file=sys.stderr);sys.exit(1)
