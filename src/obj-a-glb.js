/*
 * Convierte un modelo OBJ (con su .mtl y sus texturas) en un GLB listo para la web, dentro del navegador.
 * Lo usa la caja «Modelo 3D» del escritorio de WordPress; «npm run build» lo empaqueta con three.js en
 * tecnotron-maquinas/assets/vendor/obj-a-glb.js.
 *
 *   const { blob, medidas, avisos } = await objAGlb(archivos, { medidaCm: 198, zArriba: false });
 *
 * · archivos: el .obj y, si los hay, el .mtl y las texturas (se enlazan por nombre de archivo).
 * · medidaCm: la mayor de ancho y largo de la ficha. El modelo se escala para medir eso: tamaño real en AR.
 *   Sin ella se adivina la unidad (mm, cm o m) por el tamaño del modelo.
 * · zArriba: gira el modelo si se exportó con el eje Z hacia arriba (sale tumbado).
 */
import { Box3, Group, LoadingManager, MeshStandardMaterial, SRGBColorSpace, Vector3 } from 'three';
import { OBJLoader } from 'three/examples/jsm/loaders/OBJLoader.js';
import { MTLLoader } from 'three/examples/jsm/loaders/MTLLoader.js';
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js';

const nombreDe = ruta => decodeURIComponent(String(ruta).split('?')[0].split(/[\\/]/).pop()).toLowerCase();

export async function objAGlb(archivos, { medidaCm = 0, zArriba = false } = {}) {
  const lista = [...archivos];
  const objFile = lista.find(f => /\.obj$/i.test(f.name));
  if (!objFile) throw new Error('Falta el archivo .obj.');
  const mtlFile = lista.find(f => /\.mtl$/i.test(f.name));
  const avisos = [];

  // Cada archivo elegido se sirve desde memoria; el .mtl pide las texturas por su nombre.
  const urls = new Map(lista.map(f => [f.name.toLowerCase(), URL.createObjectURL(f)]));
  const nombrePorUrl = new Map([...urls].map(([n, u]) => [u, n]));
  const faltan = new Set();
  const manager = new LoadingManager();
  manager.setURLModifier(url => {
    if (nombrePorUrl.has(url) || url.startsWith('data:')) return url;
    const propia = urls.get(nombreDe(url));
    if (!propia) faltan.add(nombreDe(url));
    return propia || url;
  });
  // Cuenta lo que queda por cargar (texturas incluidas) para exportar sólo cuando esté todo.
  let pendientes = 0;
  let alTerminar = null;
  const itemStart = manager.itemStart.bind(manager);
  const itemEnd = manager.itemEnd.bind(manager);
  manager.itemStart = u => { pendientes++; itemStart(u); };
  manager.itemEnd = u => { pendientes--; itemEnd(u); if (!pendientes && alTerminar) alTerminar(); };

  try {
    const objLoader = new OBJLoader(manager);
    if (mtlFile) {
      const materiales = await new MTLLoader(manager).loadAsync(urls.get(mtlFile.name.toLowerCase()));
      materiales.preload();
      objLoader.setMaterials(materiales);
    } else {
      avisos.push('No has incluido el .mtl: el modelo saldrá sin colores ni texturas.');
    }
    const obj = await objLoader.loadAsync(urls.get(objFile.name.toLowerCase()));
    if (pendientes) await new Promise(ok => { alTerminar = ok; });
    if (faltan.size) avisos.push(`No se encontraron estas texturas (elígelas junto al .obj): ${[...faltan].join(', ')}.`);

    // Materiales de OBJ (Phong) → materiales PBR de glTF, que se ven igual en todos los visores
    let triangulos = 0;
    const cache = new Map();
    obj.traverse(nodo => {
      if (!nodo.isMesh) return;
      const pos = nodo.geometry.attributes.position;
      triangulos += (nodo.geometry.index ? nodo.geometry.index.count : pos.count) / 3;
      const convertir = m => {
        if (!m) return m;
        if (cache.has(m)) return cache.get(m);
        const conTextura = t => (t && t.image && (t.image.width || t.image.naturalWidth) ? t : null);
        const nuevo = new MeshStandardMaterial({
          name: m.name,
          color: m.color ? m.color.clone() : 0xffffff,
          map: conTextura(m.map),
          normalMap: conTextura(m.normalMap),
          alphaMap: conTextura(m.alphaMap),
          emissive: m.emissive ? m.emissive.clone() : 0x000000,
          emissiveMap: conTextura(m.emissiveMap),
          transparent: m.transparent || m.opacity < 1,
          opacity: m.opacity,
          side: m.side,
          vertexColors: m.vertexColors,
          roughness: 0.8,
          metalness: 0,
        });
        if (nuevo.map) nuevo.map.colorSpace = SRGBColorSpace;
        cache.set(m, nuevo);
        return nuevo;
      };
      nodo.material = Array.isArray(nodo.material) ? nodo.material.map(convertir) : convertir(nodo.material);
    });
    if (!triangulos) throw new Error('El .obj no contiene geometría.');

    // Formato de cada textura: JPEG si viene de un .jpg o es opaca; PNG si tiene transparencia
    for (const m of cache.values()) {
      for (const t of [m.map, m.normalMap, m.alphaMap, m.emissiveMap]) {
        if (!t || t.userData.mimeType) continue;
        const nombre = nombrePorUrl.get(t.image.src) || '';
        t.userData.mimeType = /\.jpe?g$/.test(nombre) || (t !== m.alphaMap && !tieneTransparencia(t.image)) ? 'image/jpeg' : 'image/png';
      }
    }

    // Orientación, escala real y apoyo en el suelo
    const raiz = new Group();
    raiz.name = objFile.name.replace(/\.obj$/i, '');
    if (zArriba) obj.rotation.x = -Math.PI / 2;
    raiz.add(obj);
    raiz.updateMatrixWorld(true);
    let caja = new Box3().setFromObject(raiz);
    const tam = caja.getSize(new Vector3());
    const lado = Math.max(tam.x, tam.z);
    let factor = 1;
    if (medidaCm > 0) {
      factor = medidaCm / 100 / lado;
    } else if (lado > 500) {
      factor = 0.001;
      avisos.push('Sin medidas en la ficha: se ha supuesto que el modelo está en milímetros.');
    } else if (lado > 20) {
      factor = 0.01;
      avisos.push('Sin medidas en la ficha: se ha supuesto que el modelo está en centímetros.');
    }
    raiz.scale.setScalar(factor);
    raiz.updateMatrixWorld(true);
    caja = new Box3().setFromObject(raiz);
    const centro = caja.getCenter(new Vector3());
    raiz.position.set(-centro.x, -caja.min.y, -centro.z);
    raiz.updateMatrixWorld(true);
    const final = new Box3().setFromObject(raiz).getSize(new Vector3());

    const glb = await new GLTFExporter().parseAsync(raiz, { binary: true, maxTextureSize: 2048, onlyVisible: true });
    return {
      blob: new Blob([glb], { type: 'model/gltf-binary' }),
      medidas: [final.x, final.z, final.y].map(v => Math.round(v * 100)), // cm: ancho × largo × alto
      triangulos: Math.round(triangulos),
      avisos,
    };
  } finally {
    for (const u of urls.values()) URL.revokeObjectURL(u);
  }
}

/** ¿La imagen tiene píxeles transparentes? (se mira en pequeño, basta para decidir el formato) */
function tieneTransparencia(img) {
  try {
    const w = Math.min(256, img.width || img.naturalWidth);
    const h = Math.max(1, Math.round((w * (img.height || img.naturalHeight)) / (img.width || img.naturalWidth)));
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, w, h);
    const d = ctx.getImageData(0, 0, w, h).data;
    for (let i = 3; i < d.length; i += 4) if (d[i] < 250) return true;
    return false;
  } catch {
    return true;
  }
}
