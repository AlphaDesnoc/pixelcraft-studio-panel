const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

async function syncIcon() {
  const source = path.join(__dirname, '..', '..', 'public', 'images', 'logo.png');
  const destDir = path.join(__dirname, '..', 'build');
  const destPng = path.join(destDir, 'icon.png');
  const destIco = path.join(destDir, 'icon.ico');

  if (!fs.existsSync(source)) {
    console.error(`Logo introuvable : ${source}`);
    process.exit(1);
  }

  fs.mkdirSync(destDir, { recursive: true });

  await sharp(source)
    .resize(512, 512, {
      fit: 'contain',
      background: { r: 0, g: 0, b: 0, alpha: 1 },
    })
    .png()
    .toFile(destPng);

  const pngToIco = (await import('png-to-ico')).default;
  const icoBuffer = await pngToIco(destPng);
  fs.writeFileSync(destIco, icoBuffer);

  console.log(`Icône synchronisée : ${destPng}`);
  console.log(`Icône Windows générée : ${destIco}`);
}

syncIcon().catch((error) => {
  console.error(error);
  process.exit(1);
});
