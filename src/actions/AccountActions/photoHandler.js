import React, { Component } from 'react';
import axios from 'axios';

class photoHandler extends Component {

    state = {
        selectedFile: null
    }
 
     fileSelectedHandler = event => {
         console.log(event.target.files[0]);
     }
     fileUploadHandler = () => {
         const fd = new FormData();
         fd.append('image', this.state.selectedFile, this.state.selectedFile.name);
         axios.post('https://localhost3001/uploads', fd, {
             onUploadProgress: progressEvent => {
                 console.log('Upload Progress:' + Math.round(progressEvent.loaded / progressEvent.total * 100) + '%');
             }
         }
         .then(res => {
             console.log(res);
         }));
     }
 
     render() {
         return (
             <div className="App">
                 <input 
                 style={{display: 'none'}} 
                 type='file' 
                 onChange={this.fileSelectedHandler}
                 ref={fileInput => this.fileInput = fileInput}/>
                 <button onClick={() => this.fileInput.click()}>Choose File</button>
                 <button onClick={this.fileUploadHandler}>Upload Photo</button>
             </div>
         );
     }
 }
 
export default photoHandler;