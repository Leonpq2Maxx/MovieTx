// Función para cambiar el capítulo automáticamente
function changeVideo(videoFile) {
    let videoPlayer = document.getElementById("videoPlayer");
    let videoSource = document.getElementById("videoSource");

    videoSource.src = videoFile;
    videoPlayer.load(); // Recargar el nuevo video
    videoPlayer.play(); // Reproducir automáticamente
}
