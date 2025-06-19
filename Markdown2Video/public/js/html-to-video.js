#!/usr/bin/env node

const { exec } = require('child_process');
const fs = require('fs');
const path = require('path');
const util = require('util');
const execPromise = util.promisify(exec);

async function mdToVideo(mdFilePath, outputVideoPath) {
  try {
    // 1. Convertir MD a imágenes usando marp-cli
    const tempDir = path.dirname(outputVideoPath);
    const imagePattern = path.join(tempDir, 'slide_%03d.png');
    
    await execPromise(`marp --image=png --output=${imagePattern} ${mdFilePath}`);
    
    // 2. Convertir imágenes a video usando FFmpeg
    await execPromise(`ffmpeg -framerate 1/3 -i ${imagePattern} -r 30 -c:v libx264 -pix_fmt yuv420p -vf "scale=1280:720" ${outputVideoPath} -y`);
    
    console.log(`Video generado exitosamente: ${outputVideoPath}`);
  } catch (error) {
    console.error('Error en la conversión:', error);
    process.exit(1);
  }
}

// Verificar argumentos
exports.mdToVideo = mdToVideo;
