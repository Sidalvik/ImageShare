// Копирование URL в буфер обмена
document.addEventListener('DOMContentLoaded', function() {
    // Автоматическое закрытие сообщений через 20 секунд
    const messages = document.querySelectorAll('.message');
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(function() {
                message.style.display = 'none';
            }, 500);
        }, 20000); // 20 секунд
    });
    
    // Управление кнопкой "Загрузить"
    const fileInput = document.getElementById('file');
    const uploadBtn = document.getElementById('upload-btn');
    
    if (fileInput && uploadBtn) {
        fileInput.addEventListener('change', function() {
            uploadBtn.disabled = !this.files || this.files.length === 0;
        });
    }

    // Работа со списком выбранных файлов
    const selectedList = document.getElementById('selected-list');
    const copySelectedBtn = document.getElementById('copy-selected');
    const checkboxes = document.querySelectorAll('.select-checkbox');
    const separatorType = document.getElementById('separator-type');
    const customSeparator = document.getElementById('custom-separator');
    
    // Модальное окно - элементы
    const modal = document.getElementById('modal');
    const modalImage = document.getElementById('modal-image');
    const modalVideo = document.getElementById('modal-video');
    
    // Функция для открытия модального окна
    const openModal = (url, type) => {
        if (!modal || !modalImage || !modalVideo) return;
        
        if (type === 'image') {
            modalImage.src = url;
            modalImage.style.display = 'block';
            modalVideo.style.display = 'none';
        } else if (type === 'video') {
            modalVideo.src = url;
            modalVideo.style.display = 'block';
            modalImage.style.display = 'none';
        }
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    // Функции для работы с sessionStorage
    const saveSelectedToStorage = () => {
        if (!selectedList) return;
        const urls = Array.from(selectedList.children).map(el => el.getAttribute('data-url'));
        sessionStorage.setItem('selectedUrls', JSON.stringify(urls));
    };

    const loadSelectedFromStorage = () => {
        try {
            const stored = sessionStorage.getItem('selectedUrls');
            if (stored) {
                return JSON.parse(stored);
            }
        } catch (e) {
            console.error('Ошибка загрузки из sessionStorage:', e);
        }
        return [];
    };

    // Функции для работы с разделителем
    const getSeparator = () => {
        if (!separatorType) return ' ';
        const type = separatorType.value;
        switch (type) {
            case 'space':
                return ' ';
            case 'comma':
                return ',';
            case 'semicolon':
                return ';';
            case 'custom':
                return customSeparator ? customSeparator.value : ' ';
            default:
                return ' ';
        }
    };

    const saveSeparatorToStorage = () => {
        if (separatorType) {
            sessionStorage.setItem('separatorType', separatorType.value);
        }
        if (customSeparator) {
            sessionStorage.setItem('customSeparator', customSeparator.value);
        }
    };

    const loadSeparatorFromStorage = () => {
        try {
            const storedType = sessionStorage.getItem('separatorType');
            if (storedType && separatorType) {
                separatorType.value = storedType;
            }
            const storedCustom = sessionStorage.getItem('customSeparator');
            if (storedCustom && customSeparator) {
                customSeparator.value = storedCustom;
            }
        } catch (e) {
            console.error('Ошибка загрузки разделителя из sessionStorage:', e);
        }
    };

    const updateCopyButtonState = () => {
        if (!copySelectedBtn || !selectedList) return;
        copySelectedBtn.disabled = selectedList.children.length === 0;
    };

    const findCheckboxByUrl = (url) => {
        return Array.from(document.querySelectorAll('.select-checkbox')).find(cb => cb.getAttribute('data-url') === url);
    };

    const removeFromSelected = (url, skipCheckbox = false) => {
        if (!selectedList) return;
        const item = Array.from(selectedList.children).find(el => el.getAttribute('data-url') === url);
        if (item) {
            selectedList.removeChild(item);
        }
        if (!skipCheckbox) {
            const cb = findCheckboxByUrl(url);
            if (cb) {
                cb.checked = false;
                updateCardSelection(cb);
            }
        }
        updateCopyButtonState();
        saveSelectedToStorage();
    };

    const getFullUrl = (url) => {
        return window.location.origin + (url.startsWith('/') ? '' : '/') + url;
    };

    const addToSelected = (url, skipSave = false) => {
        if (!selectedList) return;
        const fullUrl = getFullUrl(url);
        // Не добавляем дубликаты
        if (Array.from(selectedList.children).some(el => el.getAttribute('data-url') === url)) {
            return;
        }

        const item = document.createElement('div');
        item.className = 'selected-item';
        item.setAttribute('data-url', url);

        const thumb = document.createElement('img');
        thumb.className = 'selected-thumb';
        thumb.src = url;
        thumb.alt = 'preview';
        thumb.style.cursor = 'pointer';
        
        // Определяем тип файла по расширению
        const getFileTypeFromUrl = (url) => {
            const extension = url.split('.').pop().toLowerCase();
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
            const videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
            if (imageExtensions.includes(extension)) return 'image';
            if (videoExtensions.includes(extension)) return 'video';
            return 'image'; // по умолчанию считаем изображением
        };
        
        const fileType = getFileTypeFromUrl(url);
        
        // Добавляем обработчик клика для открытия полноэкранного просмотра
        thumb.addEventListener('click', function(e) {
            e.stopPropagation();
            openModal(url, fileType);
        });

        const text = document.createElement('div');
        text.className = 'selected-url';
        text.textContent = fullUrl;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'selected-remove';
        removeBtn.textContent = 'Удалить';
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            removeFromSelected(url);
        });

        item.appendChild(thumb);
        item.appendChild(text);
        item.appendChild(removeBtn);

        selectedList.appendChild(item);
        updateCopyButtonState();
        if (!skipSave) {
            saveSelectedToStorage();
        }
    };

    // Восстановление выбранных из sessionStorage при загрузке страницы
    const restoreSelected = () => {
        const storedUrls = loadSelectedFromStorage();
        storedUrls.forEach(url => {
            // Проверяем, существует ли файл на странице
            const cb = findCheckboxByUrl(url);
            if (cb) {
                cb.checked = true;
                updateCardSelection(cb);
                addToSelected(url, true); // skipSave = true, чтобы не сохранять при восстановлении
            }
        });
        // Сохраняем один раз после восстановления всех элементов
        saveSelectedToStorage();
    };

    // Восстанавливаем выбранные после небольшой задержки, чтобы все элементы были загружены
    setTimeout(() => {
        restoreSelected();
    }, 100);

    // Функция для обновления класса карточки при изменении чекбокса
    const updateCardSelection = (checkbox) => {
        const card = checkbox.closest('.card');
        if (card) {
            if (checkbox.checked) {
                card.classList.add('card-selected');
            } else {
                card.classList.remove('card-selected');
            }
        }
    };

    checkboxes.forEach(cb => {
        // Обновляем состояние карточки при загрузке страницы
        updateCardSelection(cb);
        
        cb.addEventListener('change', function(e) {
            e.stopPropagation();
            const url = this.getAttribute('data-url');
            updateCardSelection(this);
            if (this.checked) {
                addToSelected(url);
            } else {
                removeFromSelected(url, true);
            }
        });
    });

    // Управление выбором разделителя
    if (separatorType) {
        separatorType.addEventListener('change', function() {
            if (customSeparator) {
                customSeparator.style.display = this.value === 'custom' ? 'block' : 'none';
            }
            saveSeparatorToStorage();
        });
    }

    if (customSeparator) {
        customSeparator.addEventListener('input', function() {
            saveSeparatorToStorage();
        });
    }

    // Загрузка сохранённого разделителя
    loadSeparatorFromStorage();
    if (separatorType && customSeparator) {
        customSeparator.style.display = separatorType.value === 'custom' ? 'block' : 'none';
    }

    if (copySelectedBtn) {
        copySelectedBtn.addEventListener('click', function() {
            if (!selectedList || selectedList.children.length === 0) return;
            const separator = getSeparator();
            const urls = Array.from(selectedList.children).map(el => getFullUrl(el.getAttribute('data-url'))).join(separator);
            const copyText = urls;

            const showCopiedStatus = () => {
                const originalText = copySelectedBtn.textContent;
                copySelectedBtn.textContent = 'Скопировано!';
                copySelectedBtn.classList.add('copied');
                setTimeout(() => {
                    copySelectedBtn.textContent = originalText;
                    copySelectedBtn.classList.remove('copied');
                }, 2000);
            };

            navigator.clipboard.writeText(copyText).then(() => {
                showCopiedStatus();
            }).catch(() => {
                // fallback
                const textarea = document.createElement('textarea');
                textarea.value = copyText;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showCopiedStatus();
                } catch (err) {
                    alert('Не удалось скопировать выбранные URL');
                }
                document.body.removeChild(textarea);
            });
        });
    }
    
    const copyButtons = document.querySelectorAll('.btn-copy');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            const fullUrl = window.location.origin + (url.startsWith('/') ? '' : '/') + url;
            
            // Копирование в буфер обмена
            navigator.clipboard.writeText(fullUrl).then(() => {
                // Визуальная обратная связь
                const originalText = this.textContent;
                this.textContent = 'Скопировано!';
                this.classList.add('copied');
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                console.error('Ошибка копирования:', err);
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = fullUrl;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    const originalText = this.textContent;
                    this.textContent = 'Скопировано!';
                    this.classList.add('copied');
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.classList.remove('copied');
                    }, 2000);
                } catch (err) {
                    alert('Не удалось скопировать URL');
                }
                document.body.removeChild(textArea);
            });
        });
    });
    
    // Модальное окно - дополнительные элементы
    const modalClose = document.querySelector('.modal-close');
    const previews = document.querySelectorAll('.preview');
    
    // Открытие модального окна для превью в карточках
    previews.forEach(preview => {
        preview.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            const type = this.getAttribute('data-type');
            openModal(url, type);
        });
    });
    
    // Закрытие модального окна
    modalClose.addEventListener('click', function() {
        closeModal();
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Закрытие по клавише ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
    
    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        modalImage.src = '';
        modalVideo.src = '';
    }
    
    // Удаление файла
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Предотвращаем всплытие события
            const filePath = this.getAttribute('data-path');
            const fileName = this.getAttribute('data-name');
            
            // Подтверждение удаления
            if (confirm('Вы уверены, что хотите удалить файл "' + fileName + '"?\n\nЭто действие нельзя отменить.')) {
                // Создаем форму для отправки POST запроса
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'file_path';
                input.value = filePath;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

