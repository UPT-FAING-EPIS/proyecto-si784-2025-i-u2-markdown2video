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
      console.log(`Intento ${attempt}/${retries}: Iniciando conversión HTML a video...`);

      // Verificar que el archivo HTML existe
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
          "--disable-renderer-backgrounding"
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
        console.log("No se encontraron diapositivas, capturando página completa...");
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
      await new Promise(resolve => setTimeout(resolve, 2000));
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
    // Capturar cada diapositiva como imagen
    for (let i = 0; i < slides.length; i++) {
      const slide = slides[i];

      // Hacer scroll a la diapositiva
      await slide.scrollIntoView();
      // En lugar de page.waitForTimeout(500)
      await new Promise((resolve) => setTimeout(resolve, 500));

      // Capturar screenshot de la diapositiva
      const imagePath = path.join(
        tempDir,
        `slide_${i.toString().padStart(3, "0")}.png`
      );

      await slide.screenshot({
        path: imagePath,
        type: "png",
      });

      imageFiles.push(imagePath);
      console.log(`Capturada diapositiva ${i + 1}/${slides.length}`);
    }

    // Crear video desde las imágenes usando FFmpeg
    await createVideoFromImages(imageFiles, outputPath);
  } finally {
    // Limpiar archivos temporales
    imageFiles.forEach((file) => {
      if (fs.existsSync(file)) {
        fs.unlinkSync(file);
      }
    });
  }
}

async function createVideoFromImages(imageFiles, outputPath) {
  const { exec } = require("child_process");
  const util = require("util");
  const execPromise = util.promisify(exec);

  try {
    // Crear patrón de archivos para FFmpeg
    const tempDir = path.dirname(imageFiles[0]);
    const pattern = path.join(tempDir, "slide_%03d.png");
    
    // Renombrar archivos para seguir el patrón secuencial que FFmpeg espera
    const renamedFiles = [];
    for (let i = 0; i < imageFiles.length; i++) {
      const newName = path.join(tempDir, `slide_${i.toString().padStart(3, '0')}.png`);
      fs.renameSync(imageFiles[i], newName);
      renamedFiles.push(newName);
    }

    // Comando FFmpeg usando -framerate (1/3 = una imagen cada 3 segundos)
    const command = `ffmpeg -framerate 1/3 -i "${pattern}" -c:v libx264 -pix_fmt yuv420p -vf "scale=1280:720" "${outputPath}" -y`;

    console.log("Generando video con FFmpeg usando framerate...");
    await execPromise(command);
    
    // Limpiar archivos renombrados
    renamedFiles.forEach((file) => {
      if (fs.existsSync(file)) {
        fs.unlinkSync(file);
      }
    });
    
  } catch (error) {
    console.error("Error en createVideoFromImages:", error.message);
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
