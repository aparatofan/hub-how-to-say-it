const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/hub.js','utf8').replace('  boot();', '  window.testAPI = { S, route, viewHome, viewSnake, applyState, render, loadSnake };');
const php=fs.readFileSync('data/v1c1-3-snake.php','utf8');
const items=JSON.parse(php.split("<<<'JSON'\n")[1].split('\nJSON\n')[0]);
function setup(hash='#/',reader={name:'Test',email:'test@example.test',volumes:[1]}) {
 const app={innerHTML:'',addEventListener(){},querySelectorAll(){return []},querySelector(){return null}};
 const window={HTSI_CONFIG:{api:'/api'},addEventListener(){},scrollTo(){}};
 const context={window,document:{getElementById(){return app}},location:{hash},fetch(){throw Error('Unexpected network request')},console};
 vm.runInNewContext(source,context);
 const a=window.testAPI;
 a.applyState({reader,volumes:[1,2,3].map(n=>({n,name:'Volume '+n,chapters:Array.from({length:10},(_,i)=>({n:i+1,title:'Chapter '+(i+1),map:true,games:0}))}))});
 a.S.loaded=true;
 return {a,app,context};
}
test('42 unique tasks, balanced chapters and four task types',()=>{
 assert.equal(items.length,42);assert.equal(new Set(items.map(i=>i.n)).size,42);
 for(const ch of [1,2,3])assert.equal(items.filter(i=>i.ch===ch).length,14);
 assert.equal(new Set(items.map(i=>i.tl)).size,4);
 for(const i of items)assert.ok(i.q&&i.a&&i.note);
});
test('revision row sits after 03 before 04 without changing chapter numbers',()=>{
 const {a}=setup();const html=a.viewHome();
 assert.ok(html.indexOf('href="#/1/3"')<html.indexOf('href="#/1/3/snake"'));
 assert.ok(html.indexOf('href="#/1/3/snake"')<html.indexOf('href="#/1/4"'));
 assert.equal((html.match(/class="htsi-row-num"/g)||[]).length,10);
 a.S.vol=2;assert.ok(!a.viewHome().includes('THE SNAKE'));
});
test('locked Volume 1 has no actionable game link',()=>{
 const {a}=setup('#/',{volumes:[2]});assert.ok(!a.viewHome().includes('href="#/1/3/snake"'));
});
test('snake deep link is routed independently of chapter 3',()=>{
 const {a}=setup('#/1/3/snake');assert.equal(a.route().name,'snake');
 a.S.snake={items};a.render();assert.equal((a.viewSnake().match(/data-act="snake-open"/g)||[]).length,42);
 assert.ok(!a.S.chapters['1-3']);
});
test('signed-out deep link prompts sign-in; locked deep link offers volume unlock',()=>{
 const signedOut=setup('#/1/3/snake',null);signedOut.a.render();assert.ok(signedOut.app.innerHTML.includes('data-form="login"'));
 const locked=setup('#/1/3/snake',{volumes:[2]});locked.a.render();assert.ok(locked.app.innerHTML.includes('data-form="unlock"'));assert.equal(locked.a.S.snake,null);
});
test('logout clears game data and progress',()=>{
 const {a}=setup();a.S.snake={items};a.S.snakeSeen={1:true};a.applyState({});assert.equal(a.S.snake,null);assert.equal(Object.keys(a.S.snakeSeen).length,0);
});
test('load error exposes a retry action',()=>{const {a}=setup();a.S.snake={error:'Offline'};assert.ok(a.viewSnake().includes('data-act="snake-retry"'));});
test('late API reply cannot restore game data after logout',async()=>{
 const {a,context}=setup('#/1/3/snake');let resolve;
 context.fetch=()=>new Promise(r=>{resolve=r});a.loadSnake();a.applyState({});
 resolve({ok:true,json:async()=>({items})});await new Promise(r=>setImmediate(r));assert.equal(a.S.snake,null);
});
