document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('[data-open]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.open)?.showModal()));
 document.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
 document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();}));
 document.querySelectorAll('[data-delete-type]').forEach(button=>button.addEventListener('click',()=>{const dialog=document.getElementById('delete-dialog');const type=button.dataset.deleteType;dialog.querySelector('[name="entity_type"]').value=type;dialog.querySelector('[name="entity_id"]').value=button.dataset.deleteId;dialog.querySelector('.ds-delete-copy').textContent=`This cannot be undone. Type DELETE ${type.toUpperCase()} to confirm.`;dialog.querySelector('[name="verification"]').value='';dialog.showModal();}));
 document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{await navigator.clipboard.writeText(button.dataset.copy);const old=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=old,1200);}));
});
