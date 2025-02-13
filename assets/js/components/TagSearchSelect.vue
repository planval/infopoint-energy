<template>
  <div class="tag-selector-component">
    <!-- Selected Tags -->
    <div class="tag-selector-component-selection">
      <div
        @click="removeTag(tag)"
        v-for="tag in groupOptions"
        :key="tag.id"
        class="tag-selector-component-selection-tag"
      >
        {{ tag.name || "Unnamed Tag" }}
      </div>
    </div>

    <!-- Search Input -->
    <input
      class="tag-selector-component-selection-search"
      type="text"
      v-model="searchTerm"
      @focus="showDropdown"
      @input="filterTags"
      @blur="hideDropdown"
      :placeholder="placeholder || 'Suchen oder erstellen...'"
    />

    <!-- Dropdown for available options -->
    <div
      class="tag-selector-component-options"
      v-if="(isDropdownVisible && filteredOptions.length) || searchTerm"
    >
      <div
        class="tag-selector-component-options-option"
        v-for="option in filteredOptions"
        :key="option.id"
        @mousedown="selectTag(option)"
      >
        {{ option.name }}
      </div>
      <div
        class="tag-selector-component-options-option"
        v-if="!isTagExists && searchTerm"
      >
        <span @mousedown="createTag" class="create-new-tag">Neu erstellen "{{ searchTerm }}"</span>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios";

export default {
  emits: ["change", "tagCreated"],
  props: {
    model: {
      type: Array,
      default: () => [],
    },
    options: {
      type: Array,
      default: () => [],
    },
    context: {
      type: String,
      default: "",
    },
    type: {
      type: String,
      default: "tag",
    },
    placeholder: {
      type: String,
      default: "",
    }
  },
  data() {
    return {
      searchTerm: "",
      filteredOptions: [],
      isDropdownVisible: false,
    };
  },
  mounted() {
    this.filteredOptions = this.options.filter(
      (option) => !this.model.some((selectedTag) => selectedTag.id === option.id)
    );
  },
  methods: {
    filterTags() {
      if (this.searchTerm.trim()) {
        this.filteredOptions = this.options.filter(
          (option) =>
            option.name.toLowerCase().includes(this.searchTerm.toLowerCase()) &&
            !this.model.some((selectedTag) => selectedTag.id === option.id)
        );
      } else {
        this.filteredOptions = this.options.filter(
          (option) => !this.model.some((selectedTag) => selectedTag.id === option.id)
        );
      }
    },
    selectTag(tag) {
      if (!this.model.some((selectedTag) => selectedTag.id === tag.id)) {
        this.model.push(tag);
        this.$emit("change", this.model);
      }
      this.searchTerm = "";
      this.isDropdownVisible = false;
      this.filteredOptions = this.options.filter(
        (option) => !this.model.some((selectedTag) => selectedTag.id === option.id)
      );
    },
    removeTag(tag) {
      const index = this.model.findIndex((selectedTag) => selectedTag.id === tag.id);
      if (index !== -1) {
        this.model.splice(index, 1);
        this.$emit("change", [...this.model]);
      }
      this.filteredOptions = this.options.filter(
        (option) => !this.model.some((selectedTag) => selectedTag.id === option.id)
      );
    },
    createTag() {
      const endpoint = this.getEndpointByType();
      console.log('Creating new tag with endpoint:', endpoint);
      if (!endpoint) {
        console.error('No endpoint found for type:', this.type);
        return;
      }

      const payload = { 
        name: this.searchTerm, 
        context: this.context 
      };
      console.log('Sending payload:', payload);

      axios
        .post(endpoint, payload)
        .then((response) => {
          console.log('API Response:', response);
          const newTag = response.data;
          if (newTag && newTag.name) {
            this.model.push(newTag);
            this.options.push(newTag);
            this.$emit("change", [...this.model]);
            this.$emit("tagCreated", { type: this.type, tag: newTag });
          }
          this.searchTerm = "";
          this.filteredOptions = this.options.filter(
            (option) => !this.model.some((selectedTag) => selectedTag.id === option.id)
          );
          this.isDropdownVisible = false;
        })
        .catch((error) => {
          console.error("Error creating item:", error);
          console.error("Error details:", {
            status: error.response?.status,
            data: error.response?.data,
            config: error.config
          });
        });
    },
    getEndpointByType() {
      const endpoints = {
        'instrument': '/api/v1/instruments/create',
        'beneficiary': '/api/v1/beneficiaries/create',
        'authority': '/api/v1/authorities/create',
        'tag': '/api/v1/tags/create'
      };
      return endpoints[this.type];
    },
    showDropdown() {
      this.isDropdownVisible = true;
      this.filteredOptions = this.options.filter(
        (option) => !this.model.some((selectedTag) => selectedTag.id === option.id)
      );
    },
    hideDropdown() {
      setTimeout(() => {
        this.isDropdownVisible = false;
      }, 100); // Delay to ensure mousedown events complete before hiding
    },
  },
  computed: {
    isTagExists() {
      return this.options.some(
        (option) => option.name.toLowerCase() === this.searchTerm.toLowerCase()
      );
    },
    groupOptions() {
      if (!this.model) return [];
      let groupOptions = [];
      for (let option of this.model) {
        for (let opt of this.options) {
          if (opt.id === option.id) {
            groupOptions.push({
              ...opt,
            });
          }
        }
      }
      return groupOptions;
    },
  },
};
</script>

<style scoped>
.tag-selector-component {
  position: relative;
  width: 100%;
  max-width: 500px;
}

.tag-selector-component-selection {
  display: flex;
  flex-wrap: wrap;
}

.tag-selector-component-selection-tag {
  background-color: #E53940;
  color: white;
  padding: 5px 10px;
  border-radius: 0.25em;
  display: inline-flex;
  align-items: center;
  margin-right: 5px;
  margin-bottom: 5px;
}

.tag-selector-component-selection-tag:hover {
  background-color: #dc0000;
}

.remove-tag {
  margin-left: 8px;
  cursor: pointer;
}

.tag-selector-component-selection-search {
  width: 98% !important;
  border: 1px solid #E53940;
  border-radius: 0.25em;
  box-sizing: border-box;
}

.tag-selector-component-options {
  position: absolute;
  background-color: white;
  border: 1px solid #000000;
  border-radius: 0.25em;
  width: 100%;
  max-height: 200px;
  overflow-y: auto;
  z-index: 1;
  padding: 10px;
  display: flex;
  flex-wrap: wrap;
  box-sizing: border-box;
  margin-left: -4px;
}

.tag-selector-component-options-option {
  padding: 5px 10px;
  margin: 5px;
  border-radius: 0.25em;
  border: 1px solid #E53940;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
}

.tag-selector-component-options-option:hover {
  background-color: #E53940;
  color: white;
}

.create-new-tag {
  padding: 5px 10px;
  margin: 5px;
  font-weight: bold;
  border-radius: 0.25em;
  border: 1px solid #E53940;
  cursor: pointer;
}

.create-new-tag:hover {
  background-color: #E53940;
  color: white;
}

.tag-selector-component-selection {
  padding: 0px;
}

.tag-selector-component {
  padding: 0.25em;
}
</style> 