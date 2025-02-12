<template>

    <div class="image-selector-component">

        <div class="image-selector-component-item" v-for="(item, key) in items">

            <img v-if="item.id" :src="'/api/v1/files/view/' + item.id + '.' + item.extension" alt="">
            <img v-else :src="spinnerGif" alt="">

            <div class="image-selector-component-item-remove">
                <span class="material-icons" @click="clickToggleInfo(key)">info</span>
                <span class="material-icons error" @click="clickRemoveItem(key)" v-if="!readonly">cancel</span>
            </div>

            <div class="image-selector-component-item-move" v-if="!readonly">
                <span class="material-icons" v-if="key !== 0" @click="clickMoveLeft(key)">keyboard_arrow_left</span>
                <span class="material-icons" v-if="key + 1 !== items.length" @click="clickMoveRight(key)">keyboard_arrow_right</span>
            </div>

            <div class="image-selector-component-item-overlay" v-if="infoToggles[key]" @click="infoToggles[key] = false">
                <input type="text" class="form-control" v-model="item.description" placeholder="Beschreibung" @click.stop v-if="locale === 'de'">
                <input type="text" class="form-control" v-model="item['description'+locale.toUpperCase()]" placeholder="Beschreibung" @click.stop v-else>
                <input type="text" class="form-control" v-model="item.copyright" placeholder="Copyright" @click.stop>
            </div>

        </div>

        <div class="image-selector-component-item" v-if="item">

            <img v-if="item.id" :src="'/api/v1/files/view/' + item.id + '.' + item.extension" alt="">
            <img v-else :src="spinnerGif" alt="">

            <div class="image-selector-component-item-remove">
                <span class="material-icons error" @click="$emit('changed', null)" v-if="!readonly">cancel</span>
            </div>

        </div>

        <label class="image-selector-component-add" :for="'upload-' + rand" v-if="!readonly && (multiple || !item)">
            <span class="material-icons">add</span>
        </label>

        <input type="file" :id="'upload-' + rand" ref="upload" @change="addImage()" :multiple="multiple"
               :accept="allowedTypes">
    </div>

</template>

<script>
    export default {
        props: {
            item: {
                type: Object,
                required: false,
                default: null,
            },
            items: {
                type: Array,
                default: [],
            },
            allowedTypes: {
                type: String,
                default: '.jpg,.png,.gif',
            },
            locale: {
                type: String,
                default: 'de',
            },
            readonly: {
                type: Boolean,
                required: false,
                default: false,
            },
            multiple: {
                type: Boolean,
                required: false,
                default: true,
            },
        },
        data () {
            return {
                rand: Math.random(),
                infoToggles: {},
                spinnerGif: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPgo8c3ZnIHdpZHRoPSI0MHB4IiBoZWlnaHQ9IjQwcHgiIHZpZXdCb3g9IjAgMCA0MCA0MCIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4bWw6c3BhY2U9InByZXNlcnZlIiBzdHlsZT0iZmlsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7c3Ryb2tlLWxpbmVqb2luOnJvdW5kO3N0cm9rZS1taXRlcmxpbWl0OjEuNDE0MjE7IiB4PSIwcHgiIHk9IjBweCI+CiAgICA8ZGVmcz4KICAgICAgICA8c3R5bGUgdHlwZT0idGV4dC9jc3MiPjwhW0NEQVRBWwogICAgICAgICAgICBALXdlYmtpdC1rZXlmcmFtZXMgc3BpbiB7CiAgICAgICAgICAgICAgZnJvbSB7CiAgICAgICAgICAgICAgICAtd2Via2l0LXRyYW5zZm9ybTogcm90YXRlKDBkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICAgIHRvIHsKICAgICAgICAgICAgICAgIC13ZWJraXQtdHJhbnNmb3JtOiByb3RhdGUoLTM1OWRlZykKICAgICAgICAgICAgICB9CiAgICAgICAgICAgIH0KICAgICAgICAgICAgQGtleWZyYW1lcyBzcGluIHsKICAgICAgICAgICAgICBmcm9tIHsKICAgICAgICAgICAgICAgIHRyYW5zZm9ybTogcm90YXRlKDBkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICAgIHRvIHsKICAgICAgICAgICAgICAgIHRyYW5zZm9ybTogcm90YXRlKC0zNTlkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICB9CiAgICAgICAgICAgIHN2ZyB7CiAgICAgICAgICAgICAgICAtd2Via2l0LXRyYW5zZm9ybS1vcmlnaW46IDUwJSA1MCU7CiAgICAgICAgICAgICAgICAtd2Via2l0LWFuaW1hdGlvbjogc3BpbiAxLjVzIGxpbmVhciBpbmZpbml0ZTsKICAgICAgICAgICAgICAgIC13ZWJraXQtYmFja2ZhY2UtdmlzaWJpbGl0eTogaGlkZGVuOwogICAgICAgICAgICAgICAgYW5pbWF0aW9uOiBzcGluIDEuNXMgbGluZWFyIGluZmluaXRlOwogICAgICAgICAgICB9CiAgICAgICAgXV0+PC9zdHlsZT4KICAgIDwvZGVmcz4KICAgIDxnIGlkPSJvdXRlciI+CiAgICAgICAgPGc+CiAgICAgICAgICAgIDxwYXRoIGQ9Ik0yMCwwQzIyLjIwNTgsMCAyMy45OTM5LDEuNzg4MTMgMjMuOTkzOSwzLjk5MzlDMjMuOTkzOSw2LjE5OTY4IDIyLjIwNTgsNy45ODc4MSAyMCw3Ljk4NzgxQzE3Ljc5NDIsNy45ODc4MSAxNi4wMDYxLDYuMTk5NjggMTYuMDA2MSwzLjk5MzlDMTYuMDA2MSwxLjc4ODEzIDE3Ljc5NDIsMCAyMCwwWiIgc3R5bGU9ImZpbGw6YmxhY2s7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNNS44NTc4Niw1Ljg1Nzg2QzcuNDE3NTgsNC4yOTgxNSA5Ljk0NjM4LDQuMjk4MTUgMTEuNTA2MSw1Ljg1Nzg2QzEzLjA2NTgsNy40MTc1OCAxMy4wNjU4LDkuOTQ2MzggMTEuNTA2MSwxMS41MDYxQzkuOTQ2MzgsMTMuMDY1OCA3LjQxNzU4LDEzLjA2NTggNS44NTc4NiwxMS41MDYxQzQuMjk4MTUsOS45NDYzOCA0LjI5ODE1LDcuNDE3NTggNS44NTc4Niw1Ljg1Nzg2WiIgc3R5bGU9ImZpbGw6cmdiKDIxMCwyMTAsMjEwKTsiLz4KICAgICAgICA8L2c+CiAgICAgICAgPGc+CiAgICAgICAgICAgIDxwYXRoIGQ9Ik0yMCwzMi4wMTIyQzIyLjIwNTgsMzIuMDEyMiAyMy45OTM5LDMzLjgwMDMgMjMuOTkzOSwzNi4wMDYxQzIzLjk5MzksMzguMjExOSAyMi4yMDU4LDQwIDIwLDQwQzE3Ljc5NDIsNDAgMTYuMDA2MSwzOC4yMTE5IDE2LjAwNjEsMzYuMDA2MUMxNi4wMDYxLDMzLjgwMDMgMTcuNzk0MiwzMi4wMTIyIDIwLDMyLjAxMjJaIiBzdHlsZT0iZmlsbDpyZ2IoMTMwLDEzMCwxMzApOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTI4LjQ5MzksMjguNDkzOUMzMC4wNTM2LDI2LjkzNDIgMzIuNTgyNCwyNi45MzQyIDM0LjE0MjEsMjguNDkzOUMzNS43MDE5LDMwLjA1MzYgMzUuNzAxOSwzMi41ODI0IDM0LjE0MjEsMzQuMTQyMUMzMi41ODI0LDM1LjcwMTkgMzAuMDUzNiwzNS43MDE5IDI4LjQ5MzksMzQuMTQyMUMyNi45MzQyLDMyLjU4MjQgMjYuOTM0MiwzMC4wNTM2IDI4LjQ5MzksMjguNDkzOVoiIHN0eWxlPSJmaWxsOnJnYigxMDEsMTAxLDEwMSk7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNMy45OTM5LDE2LjAwNjFDNi4xOTk2OCwxNi4wMDYxIDcuOTg3ODEsMTcuNzk0MiA3Ljk4NzgxLDIwQzcuOTg3ODEsMjIuMjA1OCA2LjE5OTY4LDIzLjk5MzkgMy45OTM5LDIzLjk5MzlDMS43ODgxMywyMy45OTM5IDAsMjIuMjA1OCAwLDIwQzAsMTcuNzk0MiAxLjc4ODEzLDE2LjAwNjEgMy45OTM5LDE2LjAwNjFaIiBzdHlsZT0iZmlsbDpyZ2IoMTg3LDE4NywxODcpOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTUuODU3ODYsMjguNDkzOUM3LjQxNzU4LDI2LjkzNDIgOS45NDYzOCwyNi45MzQyIDExLjUwNjEsMjguNDkzOUMxMy4wNjU4LDMwLjA1MzYgMTMuMDY1OCwzMi41ODI0IDExLjUwNjEsMzQuMTQyMUM5Ljk0NjM4LDM1LjcwMTkgNy40MTc1OCwzNS43MDE5IDUuODU3ODYsMzQuMTQyMUM0LjI5ODE1LDMyLjU4MjQgNC4yOTgxNSwzMC4wNTM2IDUuODU3ODYsMjguNDkzOVoiIHN0eWxlPSJmaWxsOnJnYigxNjQsMTY0LDE2NCk7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNMzYuMDA2MSwxNi4wMDYxQzM4LjIxMTksMTYuMDA2MSA0MCwxNy43OTQyIDQwLDIwQzQwLDIyLjIwNTggMzguMjExOSwyMy45OTM5IDM2LjAwNjEsMjMuOTkzOUMzMy44MDAzLDIzLjk5MzkgMzIuMDEyMiwyMi4yMDU4IDMyLjAxMjIsMjBDMzIuMDEyMiwxNy43OTQyIDMzLjgwMDMsMTYuMDA2MSAzNi4wMDYxLDE2LjAwNjFaIiBzdHlsZT0iZmlsbDpyZ2IoNzQsNzQsNzQpOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTI4LjQ5MzksNS44NTc4NkMzMC4wNTM2LDQuMjk4MTUgMzIuNTgyNCw0LjI5ODE1IDM0LjE0MjEsNS44NTc4NkMzNS43MDE5LDcuNDE3NTggMzUuNzAxOSw5Ljk0NjM4IDM0LjE0MjEsMTEuNTA2MUMzMi41ODI0LDEzLjA2NTggMzAuMDUzNiwxMy4wNjU4IDI4LjQ5MzksMTEuNTA2MUMyNi45MzQyLDkuOTQ2MzggMjYuOTM0Miw3LjQxNzU4IDI4LjQ5MzksNS44NTc4NloiIHN0eWxlPSJmaWxsOnJnYig1MCw1MCw1MCk7Ii8+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4K',
            };
        },
        methods: {
            addImage () {
                let files = this.$refs.upload.files;
                for(let file of files) {
                    let reader = new FileReader();
                    reader.readAsDataURL(file);
                    reader.onload = () => {

                        if(file.size > 2e+7) {
                            return alert('Die ausgewählte Datei "'+file.name+'" überschreitet das Uploadlimit von 20 MB.');
                        }

                        if(!['image/jpeg', 'image/gif', 'image/png', 'image/svg+xml'].includes(file.type)) {
                            return alert('Der Dateityp "'+file.type+'" von "'+file.name+'" ist nicht erlaubt.');
                        }

                        let item = {
                            name: file.name,
                            description: '',
                            copyright: '',
                            data: null,
                            mimeType: file.type,
                            extension: file.name.split('.')[1] ? file.name.split('.')[file.name.split('.').length-1] : '',
                            loading: true,
                        };
                        item.extension = item.extension.toLowerCase();

                        if(this.multiple) {
                            this.items.push(item);
                        }

                        this.resizeImage(reader.result, file.type, (image) => {

                            item.data = image;

                            this.$store.dispatch('files/create', item).then((file) => {

                                if(!this.multiple) {
                                    this.$emit('changed', {...file});
                                    return;
                                }

                                this.items[this.items.indexOf(item)] = {...file};
                                this.$emit('changed', this.items);

                            });

                        });
                    };
                }
                this.$refs.upload.value = null;
            },
            resizeImage(base64, mimeType, onComplete) {
                if(!['image/jpeg', 'image/png', 'image/gif'].includes(mimeType)) {
                    return onComplete(base64);
                }

                let maxWidth = 1200;
                let maxHeight = 1200;
                const image = new Image();
                image.src = base64;
                image.onload = () => {
                    let ratio = image.height / image.width;
                    let width = maxWidth;
                    let height = Math.floor(maxWidth * ratio);
                    if(ratio > 1) {
                        ratio = image.width / image.height;
                        width = Math.floor(maxHeight * ratio);
                        height = maxHeight;
                    }
                    let canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    let context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0, width, height);
                    return onComplete(canvas.toDataURL(mimeType, .4));
                };
            },
            clickMoveLeft(index) {
                [this.items[index], this.items[index-1]] = [this.items[index-1], this.items[index]]
            },
            clickMoveRight(index) {
                [this.items[index], this.items[index+1]] = [this.items[index+1], this.items[index]];
            },
            clickToggleInfo(index) {
                this.infoToggles[index] = !this.infoToggles[index];
            },
            clickRemoveItem(index) {
                this.items.splice(index, 1);
                this.$emit('changed', this.items);
            },
        },
        created () {
            this.rand = Math.random();
        },
    }
</script>