#!/usr/bin/env node

/**
 * Script para convertir HTML de Marp a video MP4
 * Uso: node html-to-video.js <archivo-html> <archivo-salida>
 */

const puppeteer = require("puppeteer");
const fs = require("fs");
const path = require("path");

async function htmlToVideo(htmlFilePath, outputVideoPath) {
  let browser;
  let retries = 3;

  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      console.log(
        `Intento ${attempt}/${retries}: Iniciando conversión HTML a video...`
      );

      // Verificar  que el archivo HTML existe
      if (!fs.existsSync(htmlFilePath)) {
        throw new Error(`Archivo HTML no encontrado: ${htmlFilePath}`);
      }

      // Lanzar navegador con configuración más robusta
      browser = await puppeteer.launch({
        headless: true,
        args: [
          "--no-sandbox",
          "--disable-setuid-sandbox",
          "--disable-dev-shm-usage",
          "--disable-gpu",
          "--no-first-run",
          "--no-zygote",
          "--single-process",
          "--disable-web-security",
          "--disable-features=VizDisplayCompositor",
          "--disable-background-timer-throttling",
          "--disable-backgrounding-occluded-windows",
          "--disable-renderer-backgrounding",
        ],
        protocolTimeout: 180000, // 3 minutos
        timeout: 180000,
      });

      const page = await browser.newPage();

      // Configurar timeouts
      page.setDefaultTimeout(180000);
      page.setDefaultNavigationTimeout(180000);

      // Configurar viewport para video HD
      await page.setViewport({
        width: 1280,
        height: 720,
        deviceScaleFactor: 1,
      });

      // Cargar el archivo HTML
      const htmlContent = fs.readFileSync(htmlFilePath, "utf8");

      await page.setContent(htmlContent, {
        waitUntil: "domcontentloaded",
        timeout: 180000,
      });

      // Esperar a que se cargue completamente
      await new Promise((resolve) => setTimeout(resolve, 5000));

      // Buscar todas las diapositivas
      const slides = await page.$$("section, .slide, .marp-slide");

      if (slides.length === 0) {
        console.log(
          "No se encontraron diapositivas, capturando página completa..."
        );
        await capturePageAsVideo(page, outputVideoPath);
      } else {
        console.log(`Encontradas ${slides.length} diapositivas`);
        await captureSlidesAsVideo(page, slides, outputVideoPath);
      }

      console.log(`Video generado exitosamente: ${outputVideoPath}`);
      break; // Salir del bucle si fue exitoso
    } catch (error) {
      console.error(`Error en intento ${attempt}:`, error.message);

      if (browser) {
        await browser.close();
        browser = null;
      }

      if (attempt === retries) {
        console.error("Todos los intentos fallaron");
        process.exit(1);
      }

      // Esperar antes del siguiente intento
      await new Promise((resolve) => setTimeout(resolve, 2000));
    } finally {
      if (browser) {
        await browser.close();
      }
    }
  }
}

async function capturePageAsVideo(page, outputPath) {
  // Capturar una imagen de la página
  const screenshotPath = outputPath.replace(".mp4", "_screenshot.png");

  await page.screenshot({
    path: screenshotPath,
    fullPage: true,
    type: "png",
  });

  // Convertir imagen a video usando FFmpeg
  const { exec } = require("child_process");
  const util = require("util");
  const execPromise = util.promisify(exec);

  try {
    const command = `ffmpeg -loop 1 -i "${screenshotPath}" -c:v libx264 -t 10 -pix_fmt yuv420p -vf "scale=1280:720" "${outputPath}" -y`;
    await execPromise(command);

    // Limpiar imagen temporal
    if (fs.existsSync(screenshotPath)) {
      fs.unlinkSync(screenshotPath);
    }
  } catch (error) {
    console.error("Error ejecutando FFmpeg:", error.message);
    throw error;
  }
}

async function captureSlidesAsVideo(page, slides, outputPath) {
  const tempDir = path.dirname(outputPath);
  const imageFiles = [];

  try {
    for (let i = 0; i < slides.length; i++) {
      try {
        const slide = slides[i];
        const imagePath = path.join(
          tempDir,
          `slide_${i.toString().padStart(3, "0")}.png`
        );

        console.log(
          `[DEBUG] Procesando diapositiva ${i + 1} (ID: ${await slide.evaluate(
            (el) => el.id
          )})`
        );

        // 1. Forzar scroll manual (alternativa a scrollIntoView)
        const boundingBox = await slide.boundingBox();
        if (!boundingBox) {
          console.error(`[ERROR] Diapositiva ${i + 1} no tiene boundingBox.`);
          continue;
        }
        await page.evaluate((y) => window.scrollTo(0, y), boundingBox.y);

        // 2. Esperar a que la diapositiva esté en posición
        await page.waitForFunction(
          (slideId) => {
            const slide = document.querySelector(`section[id="${slideId}"]`);
            return slide && slide.getBoundingClientRect().top >= 0;
          },
          { timeout: 3000 },
          await slide.evaluate((el) => el.id)
        );

        // 3. Verificar estilos (debug)
        const style = await slide.evaluate((el) => ({
          position: window.getComputedStyle(el).position,
          opacity: window.getComputedStyle(el).opacity,
        }));
        console.log(`[DEBUG] Estilo de diapositiva ${i + 1}:`, style);

        // 4. Capturar screenshot (viewport completo recortado)
        await page.screenshot({
          path: imagePath,
          type: "png",
          clip: boundingBox,
        });

        imageFiles.push(imagePath);
        console.log(`✅ Capturada diapositiva ${i + 1}/${slides.length}`);
      } catch (error) {
        console.error(`❌ Error en diapositiva ${i + 1}:`, error.message);
        throw error; // Detener el proceso si falla
      }
    }
    // Crear video desde las imágenes usando FFmpeg
    await createVideoFromImages(imageFiles, outputPath);
  } finally {
    // // Limpiar archivos temporales
    // imageFiles.forEach((file) => {
    //   if (fs.existsSync(file)) {
    //     fs.unlinkSync(file);
    //   }
    // });
  }
}

async function createVideoFromImages(imageFiles, outputPath) {
  const { exec } = require("child_process");
  const util = require("util");
  const execPromise = util.promisify(exec);

  try {
    const tempDir = path.dirname(imageFiles[0]);
    // Los archivos ya se nombran como slide_000.png, slide_001.png, etc., por captureSlidesAsVideo.
    const pattern = path.join(tempDir, "slide_%03d.png");

    // El bucle de renombrado anterior era redundante y ha sido eliminado.
    // const renamedFiles = [];
    // for (let i = 0; i < imageFiles.length; i++) {
    //   const newName = path.join(tempDir, `slide_${i.toString().padStart(3, '0')}.png`);
    //   fs.renameSync(imageFiles[i], newName); // Esto renombraba un archivo a sí mismo.
    //   renamedFiles.push(newName);
    // }

    // Comando FFmpeg actualizado:
    // -framerate 1/3: Las imágenes de entrada se muestran durante 3 segundos cada una.
    // -i "${pattern}": Archivos de entrada que coinciden con el patrón.
    // -r 30: Establece la tasa de fotogramas del video de salida a 30 FPS. FFmpeg duplicará fotogramas según sea necesario.
    // -c:v libx264: Códec de video.
    // -pix_fmt yuv420p: Formato de píxeles para compatibilidad.
    // -vf "scale=1280:720": Escala la salida a 1280x720.
    // -y: Sobrescribe el archivo de salida si existe.
    const command = `ffmpeg -framerate 1/3 -i "${pattern}" -r 30 -c:v libx264 -pix_fmt yuv420p -vf "scale=1280:720" "${outputPath}" -y`;

    console.log("Generando video con FFmpeg. Comando:", command); // Registrar el comando
    await execPromise(command);

    // // Limpiar archivos de imagen temporales (los archivos originales de imageFiles)
    // imageFiles.forEach((file) => { // Usar imageFiles directamente para la limpieza
    //   if (fs.existsSync(file)) {
    //     fs.unlinkSync(file);
    //   }
    // });
  } catch (error) {
    console.error("Error en createVideoFromImages:", error.message);
    // Registrar stdout y stderr de FFmpeg si están disponibles en el error
    if (error.stdout) console.error("FFmpeg stdout:", error.stdout.toString());
    if (error.stderr) console.error("FFmpeg stderr:", error.stderr.toString());
    throw error;
  }
}

// Verificar argumentos de línea de comandos
if (process.argv.length < 4) {
  console.error("Uso: node html-to-video.js <archivo-html> <archivo-salida>");
  process.exit(1);
}

const htmlFile = process.argv[2];
const outputFile = process.argv[3];

// Ejecutar conversión
htmlToVideo(htmlFile, outputFile)
  .then(() => {
    console.log("Conversión completada exitosamente");
    process.exit(0);
  })
  .catch((error) => {
    console.error("Error en la conversión:", error.message);
    process.exit(1);
  });
