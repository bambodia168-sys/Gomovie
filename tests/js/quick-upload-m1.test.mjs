// Upload Studio 2.0 browser-state acceptance without third-party DOM libraries.
// The actual inline QuickUpload script runs in a simulated browser environment.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const blade = fs.readFileSync('resources/views/admin/quick-upload.blade.php', 'utf8');
const scripts = [...blade.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)];
assert.equal(scripts.length, 1, 'QuickUpload should own one inline behavior script');
const source = scripts[0][1].replaceAll('@json($autoReady)', 'false');
new vm.Script(source, { filename: 'quick-upload-client.js' });

class ClassList {
  constructor(){ this.set = new Set(); }
  add(value){this.set.add(value);}
  remove(value){this.set.delete(value);}
  contains(value){return this.set.has(value);}
  toggle(value,on){if(on)this.add(value);else this.remove(value);}
}

function mount(){
  const elements = new Map();
  function el(id){
    if(!elements.has(id)){
      const element = {
        id, hidden:false, disabled:false, value:'', textContent:'', style:{},
        dataset:{}, attributes:{}, classList:new ClassList(), listeners:{},
        addEventListener(name,handler){this.listeners[name]=handler;},
        dispatch(name,payload={}){this.listeners[name]?.({preventDefault(){},...payload});},
        setAttribute(name,value){this.attributes[name]=String(value);},
        scrollIntoView(){},
        querySelector(){return null;}
      };
      elements.set(id,element);
    }
    return elements.get(id);
  }
  const form = el('quick-upload-form');
  form.dataset={max:String(128*1048576),statusTemplate:'/upload-studio/__id__/status',resume:'',resumeAccess:''};
  form.action='/upload-studio/quick';
  form.reportValidity=()=>true;
  form.requestSubmit=()=>form.dispatch('submit');
  const file=el('video-file');
  file.name='video';
  file.files=[{name:'original-film.mp4',size:1024*1024}];
  const radio=el('access-private');radio.name='access';radio.value='private';radio.checked=true;
  const free=el('access-free');free.name='access';free.value='free';free.disabled=true;
  const paid=el('access-paid');paid.name='access';paid.value='paid';
  const title=el('video-title');title.name='title';title.value='My original film';
  const permission=el('permission-checkbox');permission.name='rights_confirmed';permission.value='1';
  const controls=[title,file,radio,free,paid,permission];
  form.querySelectorAll=(selector)=>{
    if(selector==='input[name="access"]')return [radio,free,paid];
    if(selector==='input:not([type="hidden"]),textarea')return controls;
    throw new Error('Unrecognized querySelectorAll: '+selector);
  };
  form.querySelector=selector=>{
    if(selector==='input[name="access"]:checked')return [radio,free,paid].find(x=>x.checked)||null;
    if(selector==='[name="_token"]')return {value:'synthetic-csrf'};
    throw new Error('Unrecognized querySelector: '+selector);
  };
  const progressBar=el('bar');
  el('upload-progress').querySelector=selector=>{
    assert.equal(selector,'[role="progressbar"]');
    return progressBar;
  };
  el('upload-result').hidden=true;
  el('upload-cancel').hidden=true;
  el('upload-retry').hidden=true;
  el('upload-review').hidden=true;
  el('result-watch').hidden=true;
  el('result-error').hidden=true;
  el('upload-error').hidden=true;
  const calls=[];
  const requests=[];
  const windowEvents={};
  class FakeXHR {
    static DONE=4;
    constructor(){this.upload={};this.readyState=1;this.timeout=0;requests.push(this);}
    open(method,url){this.method=method;this.url=url;}
    setRequestHeader(name,value){this.headers??={};this.headers[name]=value;}
    send(payload){this.payload=payload;calls.push('sent');}
    abort(){this.readyState=4;this.onabort?.();}
    finish(status,payload){
      this.readyState=4;this.status=status;this.responseText=JSON.stringify(payload);
      this.onload?.();
    }
  }
  class FakeFormData {
    constructor(sourceForm){
      assert.equal(sourceForm,form);
      this.snapshot={title:title.value,video:file.files[0],access:radio.value};
      assert.equal(file.disabled,false,'FormData must be captured before inputs disable');
    }
  }
  const browser={
    XMLHttpRequest:FakeXHR,
    FormData:FakeFormData,
    document:{getElementById:el},
    window:{
      XMLHttpRequest:FakeXHR,
      GomovieFeedback:{show(msg){calls.push('feedback:'+msg);}},
      location:{pathname:'/upload-studio'},
      history:{replaceState(_a,_b,url){calls.push('history:'+url);}},
      addEventListener(name,fn){windowEvents[name]=fn;},
    },
    fetch:async()=>({ok:true,json:async()=>({ok:true,status:'quarantined',public:false,publication_error:null})}),
    setInterval:()=>1,clearInterval:()=>{},
    DataTransfer:class{},
    console
  };
  vm.runInNewContext(source,browser,{timeout:1000});
  return {el,form,file,radio,requests,calls,windowEvents};
}

test('the inline browser script is syntactically valid',()=>{
  assert.ok(source.includes('new XMLHttpRequest()'));
});

test('invalid files are rejected locally without reaching the server',()=>{
  const page=mount();
  page.file.files=[{name:'archive.exe',size:100}];
  page.form.dispatch('submit');
  assert.equal(page.requests.length,0);
  assert.match(page.el('upload-error').textContent,/MP4, MOV, MKV or WebM/);
});

test('progress at 100% waits for server receipt, cancel is safe and not auto-retried',()=>{
  const page=mount();
  page.form.dispatch('submit');
  assert.equal(page.requests.length,1);
  const req=page.requests[0];
  assert.equal(req.method,'POST');
  assert.equal(req.headers.Accept,'application/json');
  assert.equal(req.headers['X-CSRF-TOKEN'],'synthetic-csrf');
  assert.equal(req.payload.snapshot.title,'My original film');
  assert.equal(page.el('upload-cancel').hidden,false);
  assert.equal(page.file.disabled,true);
  req.upload.onprogress({lengthComputable:true,loaded:100,total:100});
  assert.match(page.el('progress-text').textContent,/Waiting for a confirmed server receipt/);
  assert.notEqual(page.el('progress-text').textContent,'Upload complete');
  page.el('upload-cancel').dispatch('click');
  assert.equal(page.el('upload-cancel').hidden,true);
  assert.equal(page.el('upload-retry').hidden,true);
  assert.equal(page.el('upload-review').hidden,false);
  assert.equal(page.file.disabled,false);
  assert.match(page.el('upload-error').textContent,/Check Media Library before starting another/);
  assert.equal(page.requests.length,1);
});

test('known validation failure permits deliberate retry, never automatic retry',()=>{
  const page=mount();
  page.form.dispatch('submit');
  page.requests[0].finish(422,{message:'Invalid file',errors:{video:['Unsupported file type']}});
  assert.equal(page.requests.length,1);
  assert.equal(page.el('upload-retry').hidden,false);
  assert.equal(page.el('upload-review').hidden,true);
  assert.match(page.el('upload-error').textContent,/Unsupported file type/);
  page.el('upload-retry').dispatch('click');
  assert.equal(page.requests.length,2);
  assert.equal(page.el('upload-cancel').hidden,false);
});

test('lost response stays ambiguous until operator checks private Media Library',()=>{
  const page=mount();
  page.form.dispatch('submit');
  page.requests[0].onerror();
  assert.equal(page.requests.length,1);
  assert.equal(page.el('upload-retry').hidden,true);
  assert.equal(page.el('upload-review').hidden,false);
  assert.match(page.el('upload-error').textContent,/server may have already saved/);
});

test('confirmed receipt switches to private status without inventing public approval',()=>{
  const page=mount();
  page.form.dispatch('submit');
  page.requests[0].finish(201,{ok:true,upload_id:42,message:'Video privately received'});
  assert.equal(page.form.hidden,true);
  assert.equal(page.el('upload-result').hidden,false);
  assert.equal(page.el('progress-text').textContent,'Upload saved in private storage');
  assert.equal(page.el('result-status').textContent,'Private intake received');
  assert.ok(page.calls.includes('history:/upload-studio?ingest=42'));
  assert.equal(page.el('result-watch').hidden,true);
});
