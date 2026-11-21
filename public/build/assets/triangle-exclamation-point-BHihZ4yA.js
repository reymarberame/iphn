import{r as c}from"./app-NV10uPM2.js";/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const B=t=>t==null?void 0:t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */function E(t,e,n=[]){if(e==null)throw new Error("[lucide]: iconNode is required when icon name is used");return{name:B(t),size:24,node:e,...n.length>0?{aliases:n}:{}}}/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const j=t=>{let e="",n=!1;for(const o of t){if(o==="-"||o==="_"||o<=" "){n=e.length>0;continue}e.length===0?e+=o.toLowerCase():e+=n?o.toUpperCase():o,n=!1}return e};/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const I=t=>{const e=j(t);return e.charAt(0).toUpperCase()+e.slice(1)};/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=(...t)=>t.filter((e,n,o)=>!!e&&e.trim()!==""&&o.indexOf(e)===n).join(" ").trim();/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const r={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":2,"stroke-linecap":"round","stroke-linejoin":"round"};/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */function N(t){return t!=null}function P(t,e={}){var g,w;const n=e.attributeNames??{},o=i=>n[i]??i,l=t.size??t.width??r.width,d=t.size??t.height??r.height,h=((g=t.aliases)==null?void 0:g.filter(i=>typeof i=="string"&&i.trim()!=="").map(i=>`lucide-${i}`))??[],f=[...t.name?[`lucide-${t.name}`]:[],...h],s=((w=e.className)==null?void 0:w.split(" ").filter(Boolean))??[],k=e.includeDefaultClasses===!1?S(...s):S("lucide",...f,...s),x=e.absoluteStrokeWidth?Number(e.strokeWidth??r["stroke-width"])*Number(t.size??t.width??r.width)/Number(e.size??e.width??r.width):e.strokeWidth??r["stroke-width"];return["svg",{...Object.entries(r).reduce((i,[a,u])=>(i[o(a)]=u,i),{}),..."color"in e&&e.color&&{[o("stroke")]:e.color},..."size"in e&&N(e.size)&&{[o("width")]:e.size,[o("height")]:e.size},..."width"in e&&N(e.width)&&{[o("width")]:e.width},..."height"in e&&N(e.height)&&{[o("height")]:e.height},[o("stroke-width")]:x,...k&&{[o("class")]:k},[o("viewBox")]:`0 0 ${l} ${d}`,...e.hasA11yProp===!1?{[o("aria-hidden")]:"true"}:{},..."attributes"in e&&e.attributes},t.node.map(i=>{const[a,u,b]=i,C=e.nonScalingStroke?{[o("vector-effect")]:"non-scaling-stroke",...u}:u;return b?[a,C,b]:[a,C]})]}/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */function $(t,e={}){return P(t,{...e,attributeNames:{...e.attributeNames,class:"className","stroke-width":"strokeWidth","stroke-linecap":"strokeLinecap","stroke-linejoin":"strokeLinejoin","vector-effect":"vectorEffect"}})}/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const D=t=>{for(const e in t)if(e.startsWith("aria-")||e==="role"||e==="title")return!0;return!1},R=c.createContext({}),_=()=>c.useContext(R),q=c.forwardRef(({color:t,size:e,width:n,height:o,strokeWidth:l,absoluteStrokeWidth:d,nonScalingStroke:h,className:f="",children:s,iconNode:k=[],icon:x={node:k,aliases:[],size:24},...m},g)=>{const{size:w=24,strokeWidth:i=2,absoluteStrokeWidth:a=!1,nonScalingStroke:u=!1,color:b="currentColor",className:C=""}=_()??{},A=!!s||D(m),[W,v,y=[]]=$(x,{color:t??b,width:n??e??w,height:o??e??w,strokeWidth:l??i,absoluteStrokeWidth:d??a,nonScalingStroke:h??u,className:S(C,f),hasA11yProp:A,attributes:m});return c.createElement(W,{ref:g,...v},[...y.map(([p,L])=>c.createElement(p,L)),...Array.isArray(s)?s:[s]])});/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */function F(t,e=[],n=[]){const o=typeof t=="string"?E(t,e,n):t,l=c.forwardRef(({className:d,...h},f)=>c.createElement(q,{ref:f,icon:o,className:d,...h}));return o.name&&(l.displayName=I(o.name)),l}/**
 * @license lucide-react v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const z={name:"triangle-exclamation-point",size:24,node:[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]],aliases:["alert-triangle","triangle-alert"]};z.node;const T=F(z);export{T,F as c};
