// Isolated real-engine UI acceptance. WebKit emulation is NOT physical Safari.
import { chromium, webkit, devices } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const origin = process.env.GOMOVIE_QA_ORIGIN;
assert.equal(origin,'http://127.0.0.1:8871','Only the loopback isolated QA fixture is authorized');
const evidenceDir='artifacts/rights-evidence-browser';
await mkdir(evidenceDir,{recursive:true});

const setups=[
  {label:'chromium-desktop',engine:chromium,options:{viewport:{width:1440,height:900}}},
  {label:'chromium-mobile',engine:chromium,options:{...devices['Pixel 7']}},
  {label:'webkit-desktop',engine:webkit,options:{viewport:{width:1360,height:850}}},
  {label:'webkit-iphone-emulation',engine:webkit,options:{...devices['iPhone 13']}},
];
let failures=0;
for(const setup of setups){
  const browser=await setup.engine.launch({headless:true});
  const context=await browser.newContext({...setup.options,baseURL:origin});
  const page=await context.newPage();
  page.on('pageerror',error=>console.error('RIGHTS_BROWSER_SCRIPT_ERROR='+setup.label+':'+error.message));
  const verify=async(label,fn)=>{
    try{await fn();console.log('RIGHTS_BROWSER_ACCEPTANCE='+setup.label+':'+label+':PASS');}
    catch(error){failures++;console.error('RIGHTS_BROWSER_ACCEPTANCE='+setup.label+':'+label+':FAIL',error);}
  };
  await verify('authenticated-admin',async()=>{
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('upload-browser-qa@example.test');
    await page.locator('input[name="password"]').fill('Synthetic-Upload-Test-Only-2026');
    await Promise.all([
      page.waitForURL(u=>u.pathname==='/dashboard',{timeout:20000}),
      page.getByRole('button',{name:/Sign in/i}).click(),
    ]);
    const response=await page.goto('/dashboard/rights-evidence');
    assert.equal(response.status(),200,'Protected rights administration route must render');
    await page.locator('.gm-page-heading h2').getByText('Rights & Publication',{exact:true}).waitFor();
    assert.equal(await page.locator('table.gm-studio-table').count(),2);
    assert.equal(await page.locator('a[href*="/rights-evidence/"]').count(),0,
      'Empty fixture must not expose any private documents.');
    const dims=await page.evaluate(()=>({
      width:innerWidth,
      scroll:document.documentElement.scrollWidth
    }));
    assert.ok(dims.scroll<=dims.width+5,'Mobile layout overflow: '+JSON.stringify(dims));
    await page.screenshot({path:evidenceDir+'/'+setup.label+'-ledger.png',fullPage:true});
  });
  await verify('centered-submit-dialog',async()=>{
    await page.locator('[data-studio-open="rights-evidence-create"]').click();
    await page.locator('#rights-evidence-create').waitFor({state:'visible',timeout:8000});
    const dialog=page.locator('#rights-evidence-create');
    assert.equal(await dialog.evaluate(x=>x.open),true);
    assert.equal(await dialog.locator('input[name="document"]').count(),1);
    assert.equal(await dialog.locator('input[name="starts_at"]').count(),1);
    assert.equal(await dialog.locator('input[name="ends_at"]').count(),1);
    await page.screenshot({path:evidenceDir+'/'+setup.label+'-dialog.png',fullPage:false});
    await dialog.locator('button[data-studio-close]').first().click();
    assert.equal(await dialog.evaluate(x=>x.open),false);
  });
  await context.close();
  await browser.close();
}
if(failures) process.exit(1);
console.log('GOMOVIE_RIGHTS_CHROMIUM_AND_WEBKIT_ACCEPTANCE=PASS');
console.log('PHYSICAL_SAFARI_IPHONE_ANDROID_TABLET=NOT_TESTED');
