import{b as o,j as s}from"./app-BipVPp37.js";import{c as r}from"./ui-COvUe015.js";/**
 * @license lucide-react v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const d=r("EyeOff",[["path",{d:"M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49",key:"ct8e1f"}],["path",{d:"M14.084 14.158a3 3 0 0 1-4.242-4.242",key:"151rxh"}],["path",{d:"M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143",key:"13bj9a"}],["path",{d:"m2 2 20 20",key:"1ooewy"}]]);/**
 * @license lucide-react v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const n=r("Eye",[["path",{d:"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0",key:"1nclc0"}],["circle",{cx:"12",cy:"12",r:"3",key:"1v7zrd"}]]);function y({label:i="Password",...a}){const[e,c]=o.useState(!1),t=o.useId();return s.jsxs("span",{className:"password-field",children:[s.jsx("input",{...a,id:a.id||t,type:e?"text":"password"}),s.jsx("button",{type:"button",className:"password-toggle","aria-label":`${e?"Hide":"Show"} ${i.toLowerCase()}`,"aria-controls":a.id||t,"aria-pressed":e,onClick:()=>c(!e),children:e?s.jsx(d,{size:19}):s.jsx(n,{size:19})})]})}export{y as P};
