(() => {
function initPhotoCamera(root){
  if(!root || root.dataset.cameraReady==='1') return;
  root.dataset.cameraReady='1';

  const fileInput=root.querySelector('[data-photo-file]');
  const cameraData=root.querySelector('[data-camera-data]');
  const preview=root.querySelector('[data-photo-preview]');
  const openBtn=root.querySelector('[data-open-camera]');
  const clearBtn=root.querySelector('[data-clear-photo]');
  const modal=root.querySelector('[data-camera-modal]');
  const video=root.querySelector('[data-camera-video]');
  const canvas=root.querySelector('[data-camera-canvas]');
  const captureBtn=root.querySelector('[data-capture-camera]');
  const closeBtn=root.querySelector('[data-close-camera]');
  const switchBtn=root.querySelector('[data-switch-camera]');
  const state=root.querySelector('[data-camera-state]');

  let stream=null;
  let facing='user';

  function setState(text,kind=''){
    if(!state)return;
    state.textContent=text;
    state.className='camera-state '+kind;
  }

  function setPreview(src){
    if(!preview)return;
    preview.innerHTML='';
    const img=document.createElement('img');
    img.src=src;
    img.alt='Prévia da foto 3x4';
    preview.appendChild(img);
    preview.classList.add('has-photo');
    clearBtn?.removeAttribute('hidden');
  }

  function resetPreview(){
    if(!preview)return;
    preview.innerHTML='<span>FOTO<br>3x4</span>';
    preview.classList.remove('has-photo');
    clearBtn?.setAttribute('hidden','hidden');
  }

  function stopCamera(){
    if(stream){
      stream.getTracks().forEach(t=>t.stop());
      stream=null;
    }
    if(video) video.srcObject=null;
  }

  async function startCamera(){
    if(!navigator.mediaDevices?.getUserMedia){
      setState('A câmera não está disponível neste navegador. Use “Escolher foto”.','error');
      return;
    }
    try{
      stopCamera();
      setState('Solicitando permissão da câmera...','working');
      stream=await navigator.mediaDevices.getUserMedia({
        video:{
          facingMode:{ideal:facing},
          width:{ideal:1280},
          height:{ideal:960}
        },
        audio:false
      });
      video.srcObject=stream;
      await video.play();
      modal.hidden=false;
      document.body.classList.add('camera-open');
      setState('Câmera pronta. Centralize o rosto e os ombros dentro do quadro.','ok');
    }catch(err){
      setState('Não foi possível abrir a câmera. Autorize a câmera no navegador ou use “Escolher foto”.','error');
    }
  }

  function closeCamera(){
    stopCamera();
    if(modal)modal.hidden=true;
    document.body.classList.remove('camera-open');
  }

  function capture(){
    if(!video || !canvas || !stream || video.videoWidth<10 || video.videoHeight<10){
      setState('A câmera ainda não está pronta. Aguarde um instante.','error');
      return;
    }

    // Output is always 3:4, optimized for the GUACAS card.
    const outW=600,outH=800;
    canvas.width=outW;canvas.height=outH;
    const ctx=canvas.getContext('2d');

    const srcW=video.videoWidth,srcH=video.videoHeight;
    const targetRatio=3/4;
    const sourceRatio=srcW/srcH;
    let sx=0,sy=0,sw=srcW,sh=srcH;

    if(sourceRatio>targetRatio){
      sw=srcH*targetRatio;
      sx=(srcW-sw)/2;
    }else{
      sh=srcW/targetRatio;
      sy=(srcH-sh)/2;
    }

    ctx.drawImage(video,sx,sy,sw,sh,0,0,outW,outH);
    const data=canvas.toDataURL('image/jpeg',0.90);
    cameraData.value=data;
    if(fileInput) fileInput.value='';
    setPreview(data);
    setState('✅ Foto capturada. Ela será usada automaticamente na carteirinha.','ok');
    closeCamera();
  }

  fileInput?.addEventListener('change',()=>{
    const f=fileInput.files?.[0];
    cameraData.value='';
    if(!f){resetPreview();return;}
    if(!f.type.startsWith('image/')){
      setState('Escolha um arquivo de imagem JPG, PNG ou WEBP.','error');
      fileInput.value='';
      return;
    }
    if(f.size>5*1024*1024){
      setState('A foto deve ter no máximo 5 MB.','error');
      fileInput.value='';
      return;
    }
    const reader=new FileReader();
    reader.onload=e=>{setPreview(e.target.result);setState('✅ Foto selecionada.','ok');};
    reader.readAsDataURL(f);
  });

  openBtn?.addEventListener('click',startCamera);
  captureBtn?.addEventListener('click',capture);
  closeBtn?.addEventListener('click',closeCamera);

  switchBtn?.addEventListener('click',async()=>{
    facing=facing==='user'?'environment':'user';
    await startCamera();
  });

  clearBtn?.addEventListener('click',()=>{
    cameraData.value='';
    if(fileInput)fileInput.value='';
    resetPreview();
    setState('Foto removida da seleção. Escolha um arquivo ou tire uma nova foto.','');
  });

  modal?.addEventListener('click',e=>{
    if(e.target===modal)closeCamera();
  });

  addEventListener('beforeunload',stopCamera);
}

document.querySelectorAll('[data-photo-camera]').forEach(initPhotoCamera);
})();