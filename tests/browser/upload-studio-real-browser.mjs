// Browser acceptance of actual QuickUpload in an isolated Laravel installation.
import { chromium, devices } from 'playwright';
import { mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin = process.env.GOMOVIE_QA_ORIGIN || 'http://127.0.0.1:8871';
if (origin !== 'http://127.0.0.1:8871') throw new Error('Only the isolated loopback fixture is allowed.');
const testFile=process.env.GOMOVIE_QA_VIDEO_FILE;
assert.ok(testFile?.startsWith('/tmp/gomovie-browser-qa-'), 'Use synthetic test video only');
const artifactDir='artifacts/upload-studio-browser';
await mkdir(artifactDir,{recursive:true});
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
const email='upload-browser-qa@example.test';
const password='Synthetic-Upload-Test-Only-2026';
let failures=0;

async function login(page){
  await page.goto(origin+'/login',{waitUntil:'domcontentloaded'});
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(u=>u.pathname==='/dashboard',{timeout:20000}),
    page.getByRole('button',{name:/Sign in/i}).click()
  ]);
  const uploadResponse=await page.goto(origin+'/upload-studio',{waitUntil:'domcontentloaded'});
  const httpStatus=uploadResponse?.status()||0;
  const formCount=await page.locator('#quick-upload-form').count();
  if(httpStatus!==200 || formCount!==1){
    const heading=(await page.locator('h1').allTextContents()).slice(0,2).join(' | ');
    throw new Error('Isolated upload page unavailable: status='+httpStatus+
      ' url='+new URL(page.url()).pathname+' heading='+heading+
      ' formCount='+formCount);
  }
  await page.locator('#quick-upload-form').waitFor({state:'visible',timeout:5000});
}
function traceBrowser(page,label){
  page.on('request',request=>{
    if(request.url().includes('/upload-studio/'))
      console.log('BROWSER_API_REQUEST='+label+':'+request.method()+
        ':'+new URL(request.url()).pathname);
  });
  page.on('pageerror',error=>console.error('BROWSER_SCRIPT_ERROR='+label+':'+error.message));
  page.on('console',message=>{
    if(message.type()==='error')
      console.error('BROWSER_CONSOLE_ERROR='+label+':'+message.text().slice(0,260));
  });
  page.on('response',response=>{
    if(response.url().includes('/upload-studio/'))
      console.log('BROWSER_API_RESPONSE='+label+':'+response.status()+
        ':'+new URL(response.url()).pathname);
  });
  page.on('requestfailed',request=>{
    if(request.url().includes('/upload-studio/'))
      console.error('BROWSER_API_FAILED='+label+':'+request.failure()?.errorText);
  });
}
async function traceAfterClick(page,label){
  const data=await page.evaluate(()=>{
    const el=id=>document.getElementById(id);
    return {
      pathname:location.pathname,
      valid:el('quick-upload-form')?.checkValidity()??null,
      formHidden:el('quick-upload-form')?.hidden??null,
      progress:el('progress-text')?.textContent||null,
      progressHidden:el('upload-progress')?.hidden??null,
      retryHidden:el('upload-retry')?.hidden??null,
      reviewHidden:el('upload-review')?.hidden??null,
      errorHidden:el('upload-error')?.hidden??null,
      errorText:el('upload-error')?.textContent?.slice(0,180)||null
    };
  });
  console.log('BROWSER_AFTER_CLICK='+label+':'+JSON.stringify(data));
}
async function traceUploadForm(page,label){
  const data=await page.evaluate(()=>{
    const form=document.getElementById('quick-upload-form');
    const controls=[...form.elements];
    return {
      valid:form.checkValidity(),
      invalid:controls.filter(c=>c.willValidate&&!c.validity.valid).map(c=>c.name||c.id),
      access:form.querySelector('input[name="access"]:checked')?.value||null,
      file:fileInfo(),
      reliable:document.getElementById('upload-resumable')?.checked||false,
      csrf:!!form.querySelector('[name="_token"]')?.value
    };
    function fileInfo(){
      const f=document.getElementById('video-file').files?.[0];
      return f?{name:f.name,size:f.size}:null;
    }
  });
  console.log('BROWSER_FORM_STATE='+label+':'+JSON.stringify(data));
}
async function setFile(page,label){
  await page.locator('#video-title').fill('Browser QA '+label);
  await page.locator('#video-file').setInputFiles(testFile);
  await page.locator('input[name="access"][value="private"]').check();
  await page.locator('input[name="rights_confirmed"]').check();
}
async function check(name,cb){
  try {
    await cb();
    console.log('BROWSER_ACCEPTANCE='+name+':PASS');
    return true;
  } catch(error) {
    failures++;
    console.error('BROWSER_ACCEPTANCE='+name+':FAIL',error);
    return false;
  }
}
for (const target of [
  {label:'desktop',options:{viewport:{width:1440,height:900}}},
  {label:'mobile-emulation',options:{...devices['iPhone 13']}}
]){
  const context=await browser.newContext({...target.options,baseURL:origin});
  const page=await context.newPage();
  traceBrowser(page,target.label);
  const ready=await check(target.label+'-login-responsive',async()=>{
    await login(page);
    await setFile(page,target.label);
    if(await page.locator('#upload-resumable').count()) await page.locator('#upload-resumable').uncheck();
    await traceUploadForm(page,target.label);
    const bounds=await page.locator('.card.content').boundingBox();
    assert.ok(bounds?.width>250);
    const size=await page.evaluate(()=>({viewport:innerWidth,scroll:document.documentElement.scrollWidth}));
    assert.ok(size.scroll<=size.viewport+4,'Horizontal overflow: '+JSON.stringify(size));
    await page.screenshot({path:artifactDir+'/'+target.label+'-ready.png',fullPage:true});
  });
  if(!ready){await context.close();continue;}
  await check(target.label+'-validation-retry',async()=>{
    await page.route('**/upload-studio/quick',async route=>{
      await route.fulfill({status:422,contentType:'application/json',
        body:JSON.stringify({message:'Synthetic validation failed',errors:{video:['Synthetic unsupported upload']}})});
    });
    await page.locator('#video-submit').click();
    await page.waitForTimeout(700);
    await traceAfterClick(page,target.label+'-validation');
    await page.locator('#upload-retry').waitFor({state:'visible',timeout:6000});
    assert.match(await page.locator('#upload-error').innerText(),/Synthetic unsupported upload/);
    assert.equal(await page.locator('#upload-review').isVisible(),false);
    await page.unrouteAll({behavior:'wait'});
  });
  await check(target.label+'-connection-loss',async()=>{
    await page.route('**/upload-studio/quick',async route=>{await route.abort('failed');});
    await page.locator('#video-submit').click();
    await page.waitForTimeout(700);
    await traceAfterClick(page,target.label+'-connection');
    await page.locator('#upload-review').waitFor({state:'visible',timeout:6000});
    assert.equal(await page.locator('#upload-retry').isVisible(),false);
    assert.match(await page.locator('#upload-error').innerText(),/Check Media Library before starting another/);
    await page.unrouteAll({behavior:'wait'});
  });
  await check(target.label+'-private-ingest',async()=>{
    await page.locator('#video-submit').click();
    await page.waitForTimeout(700);
    await traceAfterClick(page,target.label+'-private');
    await page.locator('#upload-result').waitFor({state:'visible',timeout:16000});
    assert.equal(await page.locator('#result-watch').isVisible(),false);
    assert.match(await page.locator('#progress-text').innerText(),/Upload saved in private storage/);
    assert.match(await page.locator('#result-status').innerText(),/Private|quarantine/i);
    await page.screenshot({path:artifactDir+'/'+target.label+'-private-receipt.png',fullPage:true});
  });
  await context.close();
}

// In the milestone-2 isolated fixture, use actual HTTP chunk requests and
// real Chromium page reloads, not just mocked DOM/XHR state transitions.
for(const target of [
  {label:'desktop-resume',options:{viewport:{width:1440,height:900}}},
  {label:'mobile-resume',options:{...devices['iPhone 13']}}
]){
  const context=await browser.newContext({...target.options,baseURL:origin});
  const page=await context.newPage();
  traceBrowser(page,target.label);
  await check(target.label+'-real-interruption-reload-and-resume',async()=>{
    await login(page);
    const checkbox=page.locator('#upload-resumable');
    assert.equal(await checkbox.count(),1,'Resumable switch must be enabled only in isolated QA.');
    assert.equal(await checkbox.isChecked(),true);
    await setFile(page,'resumable-'+target.label);
    await traceUploadForm(page,target.label);
    let requests=0;
    await page.route('**/upload-studio/resumable/*/chunks',async route=>{
      requests++;
      if(requests===2)await route.abort('failed');
      else await route.continue();
    });
    await page.locator('#video-submit').click();
    await page.locator('#upload-retry').waitFor({state:'visible',timeout:30000});
    assert.ok(requests>=2,'Test video must cover multiple chunks.');
    assert.match(await page.locator('#upload-retry').innerText(),/Resume upload/);
    const pointer=await page.evaluate(()=>JSON.parse(sessionStorage.getItem('gomovie.resumable.v2')||'null'));
    assert.ok(pointer?.id,'Interrupted upload must persist a resumable session pointer.');
    const current=await page.evaluate(async id=>{
      const resp=await fetch('/upload-studio/resumable/'+encodeURIComponent(id),{credentials:'same-origin'});
      if(!resp.ok)throw new Error('Unable to inspect private session status');
      return resp.json();
    },pointer.id);
    assert.equal(current.status,'uploading');
    assert.ok(current.received_bytes>0 && current.received_bytes<current.size_bytes,
      'Browser should resume from a real confirmed partial chunk offset.');
    await page.unrouteAll({behavior:'wait'});
    await page.reload({waitUntil:'domcontentloaded'});
    await setFile(page,'resumable-'+target.label);
    await page.locator('#video-submit').click();
    await page.locator('#upload-result').waitFor({state:'visible',timeout:90000});
    assert.equal(await page.locator('#result-watch').isVisible(),false);
    assert.match(await page.locator('#progress-text').innerText(),/Upload saved in private storage/);
    assert.equal(await page.evaluate(()=>sessionStorage.getItem('gomovie.resumable.v2')),null);
    await page.screenshot({path:artifactDir+'/'+target.label+'-confirmed.png',fullPage:true});
  });
  await context.close();
}
await browser.close();
if(failures) process.exit(1);
console.log('GOMOVIE_REAL_CHROMIUM_UPLOAD_ACCEPTANCE=PASS');
console.log('GOMOVIE_MOBILE_STATUS=CHROMIUM_DEVICE_EMULATION_ONLY;PHYSICAL_DEVICES_PENDING');
