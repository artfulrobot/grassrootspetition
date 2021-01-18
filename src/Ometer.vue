<template>
  <div class="ipetometer" ref="ometer">
    <div class="ipetometer__domain" >
      <div class="ipetometer__bar" :style="barStyle"></div>
    </div>
    <span class="ipetometer__bignum">{{ count.toLocaleString() }}</span>
    <span class="ipetometer__words">{{stmt}}</span>
    <span class="ipetometer__target">
      <span v-show="stretchTarget && stretchTarget > target" class="stretch">Original target ({{target.toLocaleString()}}) exceeded! Let’s go for {{stretchTarget.toLocaleString()}}</span>
      <span v-show="!stretchTarget || stretchTarget < target" class="original">Target {{target.toLocaleString()}}</span>
    </span>
  </div>
</template>
<script>
export default {
  props: ['count', 'stmt', 'target', 'stretchTarget'],
  data() {
    return {
      animStart: false,
      step: 0,

      containerSize:false,
      debounce: false,
    };
  },
  computed:{
    barStyle() {
      var s = this.step;
      s = s*s;
      var t = (this.stretchTarget && this.stretchTarget>this.target) ? this.stretchTarget : this.target;
      return {
        width: (s * this.count / t * 100) + '%'
      };
    },
  },
  mounted() {
    var observer = new IntersectionObserver(this.handleIntersectionChange.bind(this), {
      // root: this.$refs.treesContainer,
      // rootMargin: '0px',
      threshold: 1.0
    });
    observer.observe(this.$refs.ometer);
  },
  methods:{
    handleIntersectionChange(entries, observer) {
      entries.forEach(e => {
        if (e.isIntersecting) {
          this.startAnimation();
        }
      });
    },
    startAnimation() {
      this.animStart = false;
      window.requestAnimationFrame(this.animate.bind(this));
    },
    animate(t) {
      if (!this.animStart) {
        this.animStart = t;
      }
      // Allow 1 s for the animation.
      this.step = Math.min(1, (t - this.animStart) / 1000);
      if (this.step < 1) {
        window.requestAnimationFrame(this.animate.bind(this));
      }
    }
  }
}
</script>
<style lang="scss">

.ipetometer {
  display: flex;
  flex-wrap:wrap;
  align-items: center;
  justify-content: space-between;
  line-height: 1;
  padding: 1rem;
  margin-bottom: 1rem;
  font-weight: bold;

  background: #eee; // todo

  .ipetometer__domain {
    flex: 0 0 100%;
    background: white;
  }
  .ipetometer__bar {
    background: #fc0;
    height: 1rem;
  }

  .ipetometer__bignum {
    flex: 0 0 auto;
    padding-right: 1rem;
    font-size:3rem;
  }
  .ipetometer__words {
    flex: 1 1 auto;
    font-size: 1rem;
  }
  .ipetometer__target {
    flex: 0 0 auto;
    font-size: 1rem;
  }
}
</style>
